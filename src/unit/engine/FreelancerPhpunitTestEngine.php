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
final class FreelancerPhpunitTestEngine
  extends FreelancerAbstractPhpunitTestEngine {

  private $shouldRunInDocker;

  public function run(): array {
    // @{method:getEnableCoverage} returns the following possible values:
    //
    //   - `false` if the user passed `--no-coverage`, explicitly disabling
    //     coverage.
    //   - `null` if the user did not pass any coverage flags. Coverage should
    //     generally be enabled if available.
    //   - `true` if the user passed `--coverage`, explicitly enabling coverage.
    $enable_coverage = $this->getEnableCoverage() ?? true;

    self::checkXdebugInstalled($enable_coverage);
    self::checkXdebugConfigured($enable_coverage);

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
      list(, , $stderr) = $future->resolve();

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
   * Retrieve the paths to the tests affected by the changes.
   *
   * @throws FilesystemException If the source or test directories do not exist.
   * @return list<string>  The paths to the affected tests.
   */
  protected function getSourceTestFiles(string $path): array {
    return [
    FlarcFilesystem::transposePath(
      dirname($path).'/'.ucfirst(
        preg_replace(
          '/^(.*)\.php$/',
          '$1Test.php',
          basename($path))),
      $this->sourceDirectory,
      $this->testDirectory),
    ];
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

    return parent::getBinaryPath($key, $default);
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


  protected function getBinaryArgs(string $config, bool $enable_coverage,
    string $junit_output, ?string $clover_output = null): array {
    $args = [];
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
    }

    return array_merge(
      $args,
      parent::getBinaryArgs(
        $config,
        $enable_coverage,
        $junit_output,
        $clover_output));
  }

  protected function generateOutputFiles(
    bool $enable_coverage,
    ?string $file_name = null): array {
    return [
      'clover' => $enable_coverage ? new TempFile() : null,
      'junit' => new TempFile(),
    ];
  }
}
