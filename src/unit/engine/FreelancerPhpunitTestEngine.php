<?php

/**
 * PHPUnit test engine.
 *
 * This is intended to be an alternative to the upstream `PhpunitTestEngine`
 * test engine which is tailored for a very specific workflow. Specifically,
 * the following assumptions have been made:
 *
 *   # All source files are located in a "source directory" and all test files
 *     are located in a "test directory".
 *   # The directory structure of the test directory mirrors the directory
 *     structure of the source directory.
 *   # All source files match the regular expression `/\.php$/`.
 *   # All test files match the regular expression `/Test\.php$/`.
 *   # The unit tests for a given source file can be found at the same location
 *     in the test directory, appending `Test` to the filename (before the file
 *     extension). If the source file begins with a lowercase character, the
 *     test file should begin with an uppercase character. The reason for this
 *     is that tests should always be in a class, and the name of the test file
 *     should match the name of the test class.
 *
 * This is probably reasonable to be upstreamed at some stage, but probably
 * only after [[https://secure.phabricator.com/T5568 | T5568: Support
 * `.arcunit`, similar to `.arclint`]].
 */
final class FreelancerPhpunitTestEngine extends ArcanistUnitTestEngine {

  private $affectedTests = [];
  private $shouldRunInDocker;
  private $sourceDirectory;
  private $testDirectory;
  private $testName;

  /**
   * Allows the unit test engine execute all tests with `arc unit --everything`.
   *
   * @return bool
   */
  protected function supportsRunAllTests() {
    return true;
  }

  public function run(): array {
    // @{method:getEnableCoverage} returns the following possible values:
    //
    //   - `false` if the user passed `--no-coverage`, explicitly disabling
    //     coverage.
    //   - `null` if the user did not pass any coverage flags. Coverage should
    //     generally be enabled if available.
    //   - `true` if the user passed `--coverage`, explicitly enabling coverage.
    $enable_coverage = $this->getEnableCoverage() ?? true;

    if ($enable_coverage !== false && !extension_loaded('xdebug')) {
      // Instructions to install Xdebug
      // https://xdebug.org/wizard
      throw new ArcanistUsageException(
        <<<'EOTEXT'
Xdebug is not installed.

Please install Xdebug to enable code coverage.

NOTE:
These instructions are specific to Ubuntu 20.04 and PHP 8.0. Use
the instructions from https://xdebug.org/wizard for your specific environment.

1. Download Xdebug
   wget https://xdebug.org/files/xdebug-3.2.2.tgz
2. Install pre-requisites
   sudo apt-get install php8.0-dev autoconf automake
3. Unpack the downloaded file
   tar -xvzf xdebug-3.2.2.tgz
4. Install Xdebug
   cd xdebug-3.2.2
   phpize
   ./configure --with-php-config=$(which php-config8.0)
   make
   sudo cp modules/xdebug.so /usr/lib/php/20200930
5. Configure Xdebug
   echo -e "zend_extension=xdebug.so\nxdebug.mode=coverage,debug" | sudo tee /etc/php/8.0/cli/conf.d/99-xdebug.ini

See https://xdebug.org/wizard for instructions on how to install Xdebug.

EOTEXT
      );
    }

    // If coverage is enabled then check the mode
    if ($enable_coverage !== false) {
      $required_modes = ['coverage', 'debug'];
      $modes = explode(',', ini_get('xdebug.mode'));
      if (array_diff($required_modes, $modes)) {
        $current_modes_str = empty($modes) ? 'No modes are set' : 'xdebug.mode='.implode(',', $modes);
        $required_modes_str = implode(',', $required_modes);
        throw new ArcanistUsageException(
"Xdebug is not configured correctly.

Expected Xdebug to be configured with the following modes:
   xdebug.mode={$required_modes_str}

Your current configuration is:
   {$current_modes_str}

Instructions to configure Xdebug:
1. Find your Xdebug configuration file
   php -i | grep xdebug.ini
2. Add or modify the following lines in your Xdebug configuration file
   xdebug.mode=coverage,debug

See https://xdebug.org/docs/all_settings#mode for more information."
        );
      }
    }

    if (getenv('RUN_PHPUNIT_IN_DOCKER')) {
      $this->shouldRunInDocker = true;
    }

    // Check whether Composer dependencies are up-to-date.
    $project_root = $this->getProjectRoot();
    $stale_dependencies = $this->getStaleDependencies(
      Filesystem::readFile($project_root.'/composer.lock'),
      Filesystem::readFile($project_root.'/vendor/composer/installed.json'));

    if (!empty($stale_dependencies)) {
      echo phutil_console_format(
        "<bg:yellow>** %s **</bg> %s\n",
        pht('WARNING'),
        pht(
          'The following Composer dependencies are out-of-date: %s. This '.
          'could cause unit test failures. Run `%s` to resolve this issue.',
          implode(', ', $stale_dependencies),
          'composer install'));
    }

    $this->setSourceDirectory(
      $this->getConfigPath('unit.phpunit.source-directory'));
    $this->setTestDirectory(
      $this->getConfigPath('unit.phpunit.test-directory'));

    if ($this->getRunAllTests()) {
      $this->setPaths([$this->testDirectory]);
    }
    $this->affectedTests = $this->getAffectedTests();

    if (empty($this->affectedTests)) {
      throw new ArcanistNoEffectException(pht('No tests to run.'));
    }

    $binary = $this->getBinaryPath('unit.phpunit.binary', 'phpunit');
    $config = $this->getConfigPath('unit.phpunit.config');

    $futures = [];
    $output  = [];

    foreach ($this->affectedTests as $source_path => $test_paths) {
      foreach ((array)$test_paths as $test_path) {
        if (!Filesystem::pathExists($test_path)) {
          continue;
        }

        $output_files = $this->generateOutputFiles($enable_coverage);
        $args = $this->getBinaryArgs(
          $config,
          $enable_coverage,
          $output_files['junit'],
          $output_files['clover']);

        $futures[$test_path] = new ExecFuture(
          '%s %Ls %s',
          $binary,
          $args,
          $test_path);

        $output[$test_path] = $output_files;
      }
    }

    $results = [];
    $failed_test_codes = [
      ArcanistUnitTestResult::RESULT_FAIL,
      ArcanistUnitTestResult::RESULT_BROKEN,
      ArcanistUnitTestResult::RESULT_UNSOUND,
    ];
    $errors = [];

    if ($this->renderer) {
      echo $this->renderer->getName($this->getConfigurationManager());
      echo "\n";
    }

    $futures = new FutureIterator($futures);

    foreach ($futures->limit(4) as $test => $future) {
      list($err, $stdout, $stderr) = $future->resolve();

      $result = $this->parseTestResults(
        $test,
        $output[$test]['junit'],
        $output[$test]['clover'],
        $stderr);

      if ($this->renderer) {
        foreach ($result as $unit_result) {
          echo $this->renderer->renderBasicResult($unit_result);

          if (in_array($unit_result->getResult(), $failed_test_codes)) {
            $errors[] = $unit_result;
          }
        }
      }

      $results[] = $result;
    }

    echo "\n\n";

    if ($this->renderer) {
      $this->renderer->printFailingUnitTests($errors);
    }

    return array_mergev($results);
  }

  /**
   * Retrieve all test files affected by the specified source files.
   *
   * Returns the paths to all test files which are affected by the source files
   * specified with @{method:setPaths}.
   *
   * @return list<string>
   */
  public function getAffectedTests() {
    $tests = [];

    foreach ($this->getPaths() as $path) {
      $tests[$path] = $this->getTestsForPath($path);
    }

    return $tests;
  }

  /**
   * Retrieve all tests affected by a specified path.
   *
   * This is an internal method which is only intended to be called from
   * @{method:getAffectedTests}.
   *
   * @param  string        The input path.
   * @return list<string>  A list of paths containing the tests affected by the
   *                       input path.
   */
  private function getTestsForPath($path) {
    $tests = [];

    if (!$this->sourceDirectory) {
      throw new PhutilInvalidStateException('setSourceDirectory');
    }
    if (!$this->testDirectory) {
      throw new PhutilInvalidStateException('setTestDirectory');
    }

    if (is_dir($path)) {
      if (FlarcFilesystem::isDescendant($path, $this->testDirectory)) {
        return $this->getTestsInDirectory($path);
      }

      if (FlarcFilesystem::isDescendant($path, $this->sourceDirectory)) {
        return $this->getTestsInDirectory(
          FlarcFilesystem::transposePath(
            $path,
            $this->sourceDirectory,
            $this->testDirectory));
      }
    }

    if (!preg_match('/\.php$/', $path)) {
      return [];
    }

    if (FlarcFilesystem::isDescendant($path, $this->testDirectory)) {
      if (preg_match('/Test\.php$/', $path)) {
        $tests[] = $path;
      }
    }

    if (FlarcFilesystem::isDescendant($path, $this->sourceDirectory)) {
      $tests[] = FlarcFilesystem::transposePath(
        dirname($path).'/'.ucfirst(
          preg_replace(
            '/^(.*)\.php$/',
            '$1Test.php',
            basename($path))),
        $this->sourceDirectory,
        $this->testDirectory);
    }

    return $tests;
  }

  /**
   * Get all test files contained within a given directory.
   *
   * Returns the paths to all test files contained within the specified
   * directory. A "test file" is any file which matches the regular expression
   * `/Test\.php$/`.
   *
   * This is an internal method which is only intended to be called from
   * @{method:getTestsForPath}.
   *
   * @param  string
   * @return list<string>
   */
  private function getTestsInDirectory($path) {
    $tests = [];
    $path = rtrim($path, '/');

    if (!Filesystem::pathExists($path)) {
      return [];
    }

    $files = id(new FileFinder($path))
      ->withType('f')
      ->withSuffix('php')
      ->find();

    foreach ($files as $file) {
      if (!preg_match('/Test\.php$/', $file)) {
        continue;
      }

      $tests[] = $path.'/'.$file;
    }

    return $tests;
  }

  /**
   * Set the source directory.
   *
   * Set the source directory. The source directory is the root directory which
   * contains source files. It is expected that the directory structure of the
   * test directory mirrors the directory structure of the source directory.
   *
   * @param  string
   * @return this
   */
  public function setSourceDirectory($source_directory) {
    $this->sourceDirectory = rtrim($source_directory, DIRECTORY_SEPARATOR).'/';
    return $this;
  }

  /**
   * Set the test directory.
   *
   * Set the test directory. The source directory is the root directory which
   * contains test files. It is expected that the directory structure of the
   * test directory mirrors the directory structure of the source directory.
   *
   * @param  string
   * @return this
   */
  public function setTestDirectory($test_directory) {
    $this->testDirectory = rtrim($test_directory, DIRECTORY_SEPARATOR).'/';
    return $this;
  }

  /**
   * Parse test results from PHPUnit JUnit report.
   *
   * @param  string                        Path to test file.
   * @param  string                        Path to PHPUnit JUnit report.
   * @param  string                        Path to PHPUnit Clover report.
   * @param  string                        Data written to `stderr`.
   * @return list<ArcanistUnitTestResult>
   */
  private function parseTestResults($path, $junit_ouput, $clover, $stderr): array {
    $results = Filesystem::readFile($junit_ouput);
    return id(new FlarcJunitTestResultParser())
      ->setEnableCoverage($clover !== null)
      ->setProjectRoot($this->getProjectRoot())
      ->setCoverageFile($clover)
      ->setAffectedTests($this->affectedTests)
      ->setStderr($stderr)
      ->parseTestResults($path, $results);
  }


/* -(  Utility  )------------------------------------------------------------ */


  /**
   * Retrieve a path to an executable from the `.arcconfig` file.
   *
   * @param  string       The configuration key.
   * @param  string|null  The default value.
   * @return string       The path to the executable.
   */
  protected function getBinaryPath(string $key,
    ?string $default = null): string {
    if ($this->shouldRunInDocker) {
      if (!Filesystem::binaryExists('docker')) {
        throw new ArcanistUsageException(
          'Docker does not seem to be installed. Please install Docker.');
      }

      return 'docker';
    }

    $bin = $this
      ->getConfigurationManager()
      ->getConfigFromAnySource($key, $default);

    $binary_path = Filesystem::resolvePath($bin, $this->getProjectRoot());

    if (!Filesystem::binaryExists($binary_path)) {
      throw new ArcanistUsageException(
        pht(
          '%s does not seem to be installed at `%s`. Have you run `%s`?',
          'PHPUnit',
          $bin,
          'composer install'));
    }

    return $binary_path;
  }

  /**
   * Retrieve a path from the `.arcconfig` file.
   *
   * Retrieves a path from the arcanist configuration (`.arcconfig`) file,
   * resolving the path relative to the project root directory.
   *
   * @param  string       The configuration key.
   * @param  string|null  The default value.
   * @return string       The absolute path to the configuration file.
   */
  protected function getConfigPath($key, $default = null) {
    $path = $this->getConfigurationManager()->getConfigFromAnySource($key);

    if (!Filesystem::pathExists($path)) {
      throw new Exception(
        pht(
          "Path '%s' was not found for '%s'.",
          $path,
          $key));
    }

    return $path;
  }

  protected function getBinaryArgs(string $config, bool $enable_coverage,
    string $junit_output, ?string $clover_output = null): array {
    if ($this->shouldRunInDocker) {
      $docker_image = $this->getConfigurationManager()
        ->getConfigFromAnySource('unit.phpunit.docker-image');
      $phpunit_path = $this->getConfigurationManager()
        ->getConfigFromAnySource('unit.phpunit.binary');

      $args = [
        'run',
        '--rm',
        '--volume='.$this->getProjectRoot().':'.$this->getProjectRoot(),
        '--volume='.$junit_output.':'.$junit_output,
        '--workdir='.$this->getProjectRoot(),
      ];

      if ($enable_coverage != false) {
        $args[] = '--volume='.$clover_output.':'.$clover_output;
      }

      $args[] = $docker_image;
      $args[] = $phpunit_path;
    } else {
      $args = [];
    }

    if ($config) {
      $args[] = '--configuration='.$config;
    }
    $args[] = '-d display_errors=stderr';
    $args[] = '--log-junit='.$junit_output;

    if ($enable_coverage !== false) {
      $args[] = '--coverage-clover='.$clover_output;
    }

    return $args;
  }

  protected function generateOutputFiles(bool $enable_coverage): array {
    return [
      'clover' => $enable_coverage ? new TempFile() : null,
      'junit' => new TempFile(),
    ];
  }

  /**
   * Retrieve the project root directory.
   *
   * This is a convenience method to access the project root directory from the
   * working copy of the unit test engine.
   *
   * @return string  The project root directory.
   */
  public function getProjectRoot() {
    return $this->getWorkingCopy()->getProjectRoot();
  }

  /**
   * Get stale Composer dependencies.
   *
   * Compare dependency versions in `composer.lock` and
   * `installed.json`, and return dependencies needed
   * to be updated or installed.
   *
   * @param  string         Content of composer.lock
   * @param  string         Content of installed.json
   * @return list<string>   Stale dependencies
   */
  public static function getStaleDependencies($composer_lock, $installed) {
    $repo_dependencies = phutil_json_decode($composer_lock);
    $repo_dependencies = $repo_dependencies['packages'];
    $local_dependencies = phutil_json_decode($installed);
    $local_dependencies = $local_dependencies && array_key_exists('packages', $local_dependencies)
      ? $local_dependencies['packages']
      : $local_dependencies;

    $local_dependency_versions = [];
    foreach ($local_dependencies as $dependency) {
      $name = $dependency['name'];
      $local_dependency_versions[$name] = $dependency['version'];
    }

    $stale_dependencies = [];
    foreach ($repo_dependencies as $dependency) {
      $name = $dependency['name'];

      if (!isset($local_dependency_versions[$name])
        || $dependency['version'] !== $local_dependency_versions[$name]) {

        $stale_dependencies[] = $name;
      }
    }

    return $stale_dependencies;
  }
}
