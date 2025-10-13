<?php

abstract class FreelancerAbstractPhpunitTestEngine
  extends ArcanistUnitTestEngine {
  private static $requiredXdebugModes = ['coverage', 'debug'];

  /** @var bool|null $xdebugInstalled */
  private static $xdebugInstalled = null;

  /** @var bool|null $isXdebugConfiguredCorrectly */
  private static $isXdebugConfiguredCorrectly = null;

  /** @var list<string> $currentXdebugModes */
  private static $currentXdebugModes = [];

  /** @var list<string> $affectedTests */
  protected $affectedTests = [];

  /** @var string $sourceDirectory */
  protected $sourceDirectory;

  /** @var string $testDirectory */
  protected $testDirectory;

  /**
   * Check if the xdebug extension is installed.
   *
   * This function checks if the xdebug extension is installed. The result of the
   * check is cached to avoid repeated loading checks. This caching behavior can
   * be influenced by the $enable_coverage parameter.
   *
   * @return bool Returns true if xdebug is installed, otherwise false.
   */
  private static function isXdebugInstalled(): bool {
    if (self::$xdebugInstalled === null) {
      self::$xdebugInstalled = extension_loaded('xdebug');
    }
    return self::$xdebugInstalled ?? false;
  }

  /**
   * Checks if the xdebug extension is installed and configured correctly.
   *
   * This function checks if the xdebug extension is installed and configured
   * correctly. The result of the check is cached to avoid repeated loading
   * checks. This caching behavior can be influenced by the $enable_coverage
   * parameter.
   *
   * @return bool Returns true if xdebug is installed and configured correctly,
   *              otherwise false.
   */
  private static function hasCorrectXdebugModes(): bool {
    if (self::$isXdebugConfiguredCorrectly === null) {
      self::$currentXdebugModes = explode(
        ',',
        ini_get('xdebug.mode') ?: '');
      self::$isXdebugConfiguredCorrectly = !array_diff(
        self::$requiredXdebugModes,
        self::$currentXdebugModes);
    }
    return self::$isXdebugConfiguredCorrectly ?? false;
  }


  /**
   * Check if Xdebug is installed.
   *
   * This function checks if Xdebug is installed. If Xdebug is not installed, an
   * exception is thrown with instructions on how to install Xdebug.
   *
   * @param bool $enable_coverage If true, checks if Xdebug is installed for
   *                              code coverage.
   *
   * @throws ArcanistUsageException If Xdebug is not installed.
   */
  protected static function checkXdebugInstalled(bool $enable_coverage): void {
    if ($enable_coverage && !self::isXdebugInstalled()) {
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
   echo -e "zend_extension=xdebug.so\nxdebug.mode=coverage,debug" \
      | sudo tee /etc/php/8.0/cli/conf.d/99-xdebug.ini

See https://xdebug.org/wizard for instructions on how to install Xdebug.

EOTEXT
      );
    }
  }

  /**
   * Check if Xdebug is configured correctly.
   *
   * This function checks if Xdebug is configured correctly. If Xdebug is not
   * configured correctly, an exception is thrown with instructions on how to
   * configure Xdebug.
   *
   * @param bool $enable_coverage If true, checks if Xdebug is configured
   *                              correctly for code coverage.
   *
   * @throws ArcanistUsageException If Xdebug is not configured correctly.
   */
  protected static function checkXdebugConfigured(bool $enable_coverage): void {
    if ($enable_coverage && !self::hasCorrectXdebugModes()) {
      $current_modes_str = empty(self::$currentXdebugModes)
        ? 'No modes are set'
        : 'xdebug.mode='.implode(',', self::$currentXdebugModes);
      $required_modes_str = implode(',', self::$requiredXdebugModes);
      throw new ArcanistUsageException(
        <<<"EOTEXT"
Xdebug is not configured correctly.

Expected Xdebug to be configured with the following modes:
   xdebug.mode={$required_modes_str}

Your current configuration is:
   {$current_modes_str}

Instructions to configure Xdebug:
1. Find your Xdebug configuration file
   php -i | grep xdebug.ini
2. Add or modify the following lines in your Xdebug configuration file
   xdebug.mode=coverage,debug

See https://xdebug.org/docs/all_settings#mode for more information.
EOTEXT
      );
    }
  }

  /**
   * Assert that the source directory has been set.
   *
   * @throws PhutilInvalidStateException If the source folder has not been set.
   * @return void
   */
  protected function assertSourceDirectory(): void {
    if (!$this->sourceDirectory) {
      throw new PhutilInvalidStateException('setSourceDirectory');
    }
  }

  /**
   * Assert that the test directory has been set.
   *
   * @throws PhutilInvalidStateException If the test directory has not been set.
   * @return void
   */
  protected function assertTestDirectory(): void {
    if (!$this->testDirectory) {
      throw new PhutilInvalidStateException('setTestDirectory');
    }
  }

  /**
   * Allows the unit test engine execute all tests with `arc unit --everything`.
   *
   * @return bool
   */
  protected function supportsRunAllTests() {
    return true;
  }

  /**
   * Retrieve all test files affected by the specified source files.
   *
   * Returns the paths to all test files which are affected by the source files
   * specified with @{method:setPaths}.
   *
   * @return list<string>
   */
  public function getAffectedTests(): array {
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
   * @param string $path The input path.
   *
   * @throws FilesystemException If the source or test directories do not exist.
   * @throws PhutilInvalidStateException If the source or test directories have
   * @return list<string>  A list of paths containing the tests affected by the
   *                       input path.
   */
  protected function getTestsForPath(string $path): array {
    $tests = [];

    $this->assertSourceDirectory();
    $this->assertTestDirectory();

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
      $tests = array_merge($tests, $this->getSourceTestFiles($path));
    }

    return $tests;
  }

  abstract protected function getSourceTestFiles(string $path): array;

  /**
   * Parse test results from PHPUnit JUnit report.
   *
   * @param string      $path         Path to test file.
   * @param string      $junit_output Path to PHPUnit JUnit report.
   * @param string|null $clover       Path to PHPUnit Clover report.
   * @param string      $stderr       Data written to `stderr`.
   *
   * @throws FilesystemException
   * @return list<ArcanistUnitTestResult>
   */
  protected function parseTestResults(
    string $path,
    string $junit_output,
    ?string $clover,
    string $stderr): array {
    $results = Filesystem::readFile($junit_output);

    $parser = new FlarcJunitTestResultParser();
    return $parser
      ->setEnableCoverage($clover !== null)
      ->setProjectRoot($this->getProjectRoot())
      ->setCoverageFile($clover)
      ->setAffectedTests($this->affectedTests)
      ->setStderr($stderr)
      ->parseTestResults($path, $results);
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
   * @param  string $path The path to the directory.
   * @return list<string>
   */
  private function getTestsInDirectory(string $path): array {
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
   * @param  string $source_directory
   */
  public function setSourceDirectory(string $source_directory): void {
    $this->sourceDirectory = rtrim(
      $source_directory,
      DIRECTORY_SEPARATOR).'/';
  }

  /**
   * Set the test directory.
   *
   * Set the test directory. The source directory is the root directory which
   * contains test files. It is expected that the directory structure of the
   * test directory mirrors the directory structure of the source directory.
   *
   * @param  string $test_directory
   */
  public function setTestDirectory(string $test_directory): void {
    $this->testDirectory = rtrim(
      $test_directory,
      DIRECTORY_SEPARATOR).'/';
  }

  protected function getBinaryArgs(
    string $config,
    bool $enable_coverage,
    string $junit_output,
    ?string $clover_output = null): array {
    $args = [];

    if ($config) {
      $args[] = "--configuration={$config}";
    }
    $args[] = '-d display_errors=stderr';
    $args[] = "--log-junit={$junit_output}";

    if ($enable_coverage !== false) {
      $args[] = "--coverage-clover={$clover_output}";
    }

    return $args;
  }

  /**
   * Retrieve the path to the binary.
   *
   * This function retrieves the path to the binary based on the key provided.
   * The key is used to retrieve the path from the configuration manager. If the
   * path does not exist, an exception is thrown.
   *
   * @param string $key The key to retrieve the path from the configuration.
   * @param string|null $default The default value to use if the key is not
   *                             found.
   *
   * @return string The path to the binary.
   *
   * @throws ArcanistUsageException Thrown if the path does not exist.
   */
  protected function getBinaryPath(string $key,
    ?string $default = null): string {
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
   * Retrieve the path to the configuration file.
   *
   * This function retrieves the path to the configuration file based on the
   * key provided. The key is used to retrieve the path from the configuration
   * manager. If the path does not exist, an exception is thrown.
   *
   * @param string $key The key to retrieve the path from the configuration.
   * @param string|null $default The default value to use if the key is not
   *                             found.
   *
   * @return string The path to the configuration file.
   *
   * @throws Exception Thrown if the path does not exist.
   */
  protected function getConfigPath($key, ?string $default = null) {
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

  /**
   * Generate output files for the test results.
   *
   * This function generates the output files for the test results. The output
   * files are generated based on the test results and the coverage settings.
   *
   * @param bool $enable_coverage If true, generates coverage files.
   * @param string|null $file_name The name of the output file.
   *
   * @return array<string> Returns the list of output files generated.
   */
  abstract protected function generateOutputFiles(
    bool $enable_coverage,
    ?string $file_name = null): array;

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

    $local_dependencies =
      $local_dependencies
      && array_key_exists('packages', $local_dependencies)
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
