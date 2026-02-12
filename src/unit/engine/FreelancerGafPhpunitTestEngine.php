<?php

/**
 * PHPUnit test engine for GAF.
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
 * GAF-Specific Implementation:
 *   # Uses `bin/run-tests` shell script which can use either `bin/gaf-php`
 *     or `vendor/bin/phpunit` depending on the `PHPUNIT` environment variable.
 *
 * This is probably reasonable to be upstreamed at some stage, but probably
 * only after [[https://secure.phabricator.com/T5568 | T5568: Support
 * `.arcunit`, similar to `.arclint`]].
 */
final class FreelancerGafPhpunitTestEngine
extends FreelancerAbstractPhpunitTestEngine {

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
    $this->setTestType(
      $this->getConfigValue('unit.phpunit.test-type'));
    $this->setReportDirectory(
      $this->getConfigValue('unit.phpunit.reports'));



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
        $test_name = $this->getUniqueBasename($test_path);
        $output_files = $this->generateOutputFiles(
          $enable_coverage,
          $test_name);

        $args = $this->getBinaryArgs(
          $config,
          $enable_coverage,
          $output_files['junit'],
          $output_files['clover']);

        $futures[$test_path] = new ExecFuture(
          '%s %s %Ls',
          $binary,
          $test_path,
          $args
        );

        $output[$test_path] = $output_files;
      }
    }

    $results = [];
    $deprecations_counter = 0;
    /** @var array<string, int> $deprecations_count_map */
    $deprecations_count_map = [];
    $failed_test_codes = [
      ArcanistUnitTestResult::RESULT_FAIL,
      ArcanistUnitTestResult::RESULT_BROKEN,
      ArcanistUnitTestResult::RESULT_UNSOUND,
    ];
    $errors = [];

    if ($this->renderer) {
      echo $this->renderer->getName($this->getConfigurationManager());
      echo PHP_EOL;
    }

    $futures = new FutureIterator($futures);

    foreach ($futures->limit(4) as $test => $future) {
      list(, $stdout, $stderr) = $future->resolve();

      $deprecations = $this->countDeprecationNotices($stdout);
      if ($deprecations > 0) {
        $deprecations_counter += $deprecations;
        if (!array_key_exists($test, $deprecations_count_map)) {
          $deprecations_count_map[$test] = $deprecations;
        } else {
          $deprecations_count_map[$test] += $deprecations;
        }
      }

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

    // Error on deprecations and describe how to address them
    if ($deprecations_counter > 0) {
      $this->printDeprecationsHelp(
        $deprecations_counter,
        $deprecations_count_map);
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
}
