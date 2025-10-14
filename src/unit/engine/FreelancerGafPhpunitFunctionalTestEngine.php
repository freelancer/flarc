<?php

/**
 * PHPUnit Functional test engine for GAF.
 *
 * FreelancerGafPhpunitFunctionalTestEngine extends ArcanistUnitTestEngine and
 * is customized for specific PHPUnit testing workflows. This engine is
 * optimized for comprehensive setup, execution, and teardown of PHPUnit tests.
 *
 * Features:
 * - Handles setup and teardown for tests via predefined commands.
 * - Maps test coverage using @covers and @coversDefaultClass annotations.
 * - Manages test execution, including handling dependencies and Xdebug
 *   configuration.
 * - Assumes structured separation between "source" and "test" directories with
 *   specific naming conventions for test files.
 *
 * Workflow:
 * 1. Set up the test environment according to the integration or functional
 *    test requirements.
 * 2. Identify and configure tests to run based on affected source files.
 * 3. Execute tests, managing Xdebug for coverage and processing results.
 * 4. Clean up the environment post-testing.
 *
 * Assumptions:
 * - Adheres to strict file naming conventions for source and test files.
 * - Utilizes @covers annotations to determine test responsibilities for class
 *   methods.
 * - Requires Xdebug for coverage metrics, prompting installation if not
 *   configured.
 *
 * GAF-Specific Implementation:
 * - Uses `bin/run-tests` shell script which can use either `bin/gaf-php`
 *   or `vendor/bin/phpunit` depending on the `PHPUNIT` environment variable.
 *
 * Usage:
 * - This class should be used in the FreelancerGafTestEngine class
 */
final class FreelancerGafPhpunitFunctionalTestEngine
extends FreelancerAbstractPhpunitTestEngine {

  /** @var string $testCoverageMap */
  private $testCoverageMap = [];

  protected function supportsRunAllTests() {
    // Disable running all tests for this engine.
    return false;
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
    } else {
      $this->testCoverageMap = $this->getTestCoverageMap();
    }

    $this->affectedTests = $this->getAffectedTests();

    $affected_tests_count = 0;
    foreach ($this->affectedTests as $test_paths) {
      foreach ((array)$test_paths as $test_path) {
        if (Filesystem::pathExists($test_path)) {
          $affected_tests_count++;
        }
      }
    }

    if ($affected_tests_count === 0) {
      throw new ArcanistNoEffectException(pht('No tests to run.'));
    }

    if ($this->renderer) {
      echo $this->renderer->getName($this->getConfigurationManager());
      echo "\n";
    }

    $binary = $this->getBinaryPath('unit.phpunit.binary', 'phpunit');
    $config = $this->getConfigPath('unit.phpunit.config');

    try {
      echo phutil_console_format(
        '<bg:blue>** %s **</bg> %s',
        pht('INFO'),
        pht(
          'Found %s test file(s). Starting test environment setup...',
          $affected_tests_count));

      $time_now = microtime(true);
      $setup_future = new ExecFuture(
        '%C',
        "{$binary} setup"
      );

      list($exit_code, , $stderr) = $setup_future->resolve();
      if ($exit_code !== 0) {
        throw new ArcanistUsageException($stderr);
      }

      $elapsed_time = microtime(true) - $time_now;
      echo pht(" Completed in %.2fs\n", $elapsed_time);

      $futures = [];
      $output = [];

      foreach ($this->affectedTests as $test_paths) {
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
            '%C %s %Ls',
            "SETUP=false {$binary}",
            $test_path,
            $args
          );

          $output[$test_path] = $output_files;
        }
      }

      $errors = [];
      $results = [];
      $failed_test_codes = [
        ArcanistUnitTestResult::RESULT_FAIL,
        ArcanistUnitTestResult::RESULT_BROKEN,
        ArcanistUnitTestResult::RESULT_UNSOUND,
      ];

      $futures = new FutureIterator($futures);
      foreach ($futures->limit(1) as $test => $future) {
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
    } finally {
      echo phutil_console_format(
        '<bg:blue>** %s **</bg> %s',
        pht('INFO'),
        pht('Tearing down test environment...'));

      $time_now = microtime(true);
      $teardown_future = new ExecFuture(
        '%C',
        "{$binary} shutdown"
      );

      list($exit_code, , $stderr) = $teardown_future->resolve();
      if ($exit_code !== 0) {
        throw new ArcanistUsageException($stderr);
      }

      $elapsed_time = microtime(true) - $time_now;
      echo pht(" Completed in %.2fs\n", $elapsed_time);
    }
  }

  /**
   * Finds relevant test files based on the test type.
   *
   * @throws Exception If the given testDirectory is not dir or not readable.
   * @return array An array of test file paths.
   */
  private function findRelevantTestFiles(): array {
    $test_finder = new FileFinder($this->testDirectory);
    $test_files = $test_finder->withType('f')->withSuffix('php')->find();
    $test_type_suffix = sprintf('%sTest.php', ucfirst($this->testType));

    return array_filter(
      $test_files,
      function (string $file) use ($test_type_suffix) {
        return str_ends_with($file, $test_type_suffix);
      });
  }

  /**
   * Extracts annotated class names from given file content.
   *
   * @param string $content The content of a file.
   * @return list<string> An array of class names extracted from annotations.
   */
  public function extractAnnotatedClasses(string $content): array {
    $matches = [];
    preg_match_all(
      '/@(covers|coversDefaultClass)\s+(\S+)/',
      $content,
      $matches,
      PREG_SET_ORDER);

    $classes = [];
    foreach ($matches as $match) {
      $class_name = $match[2];

      if (str_contains($class_name, '::')) {
        $new_class_name = explode('::', $class_name)[0];
        if ($new_class_name === '') {
          // Ignore @covers ::methodName
          continue;
        }

        $class_name = $new_class_name;
      }

      $classes[] = $class_name;
    }
    return $classes;
  }

  /**
   * Builds a reverse map of PHPUnit test coverages.
   *
   * It supports either 'FunctionalTest.php' or 'IntegrationTest.php'
   * files depending on $this->testType.
   *
   * @throws Exception If the given testDirectory is not dir or not readable.
   * @throws FilesystemException If reading from the filesystem fails.
   * @return list<string, list<string>> Map of class names to test files.
   */
  private function getTestCoverageMap(): array {
    $test_files = $this->findRelevantTestFiles();
    $coverage_map = [];

    foreach ($test_files as $file_path) {
      $relative_path = $this->testDirectory.$file_path;
      $content = Filesystem::readFile($relative_path);
      $classes = $this->extractAnnotatedClasses($content);
      foreach ($classes as $class_name) {
        $coverage_map[$class_name][] = $relative_path;
      }
    }

    // Remove duplicate file entries for each class
    foreach ($coverage_map as $class => $files) {
      $coverage_map[$class] = array_values(array_unique($files));
    }

    return $coverage_map;
  }

  /**
   * Retrieves the test files associated with the given source file.
   *
   * @param string $path The path to the source file.
   *
   * @throws FilesystemException If reading from the filesystem fails.
   * @return list<string> An array of test file paths.
   */
  protected function getSourceTestFiles(string $path): array {
    if (Filesystem::pathExists($path) === false) {
      return [];
    }

    $affected_file_name = basename($path, '.php');
    $content = Filesystem::readFile($path);

    $matches = [];

    // Extract namespace from source file
    preg_match('/namespace\s+(.*);/', $content, $matches);
    $namespace = '';
    if (!empty($matches)) {
      $namespace = "\\{$matches[1]}" ?? '';
    }

    $fnq_class_came = "{$namespace}\\{$affected_file_name}";
    $function_tests = $this->testCoverageMap[$fnq_class_came] ?? [];
    $tests = [];
    foreach ($function_tests as $functional_test) {
      $tests[] = $functional_test;
    }

    return $tests;
  }
}
