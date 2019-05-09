<?php

/**
 * Test result parser for JUnit.
 *
 * This class parses JUnit test results and Clover XML coverage data - for
 * example, as produced by PHPUnit with the `--log-junit` and
 * `--coverage-clover` flags.
 * This class is based on @{class:ArcanistPhpunitTestResultParser}.
 *
 * @task config   Configuration
 * @task parser   Test Result Parsing
 * @task utility  Utility
 */
final class FlarcJunitTestResultParser extends ArcanistTestResultParser {

  private $fileLineCounts = [];


/* -(  Configuration  )------------------------------------------------------ */


  /**
   * Get the number of lines contained within a file.
   *
   * Calculate the number of lines contained with a file. The count is cached
   * in order to avoid unnecessary computations.
   *
   * The line count for a file can be faked by calling
   * @{method:setFileLineCount}, however this is only intended to be used for
   * unit testing purposes.
   *
   * @param  string  The path.
   * @return int     The number of lines contained within the specified file.
   *
   * @task config
   */
  public function getFileLineCount($path) {
    if (!isset($this->fileLineCounts[$path])) {
      $path = Filesystem::resolvePath($path, $this->projectRoot);
      $contents = Filesystem::readFile($path);
      $this->setFileLineCount($path, count(phutil_split_lines($contents)));
    }

    return $this->fileLineCounts[$path];
  }

  /**
   * Set the number of lines contained within a file.
   *
   * See @{method:getFileLineCount} for details.
   *
   * NOTE: This method is intended only to be used within unit tests.
   *
   * @param  string  The path.
   * @param  int     The number of lines contained within the specified file.
   * @return this
   *
   * @task config
   */
  public function setFileLineCount($path, $count) {
    $this->fileLineCounts[$path] = $count;
    return $this;
  }


/* -(  Test Result Parsing  )------------------------------------------------ */


  /**
   * Parse test results from the JUnit report.
   *
   * This method converts output from JUnit to instances of
   * @{class:ArcanistUnitTestResult}.
   *
   * @param  string                        Path of the test file.
   * @param  string                        String containing the JUnit
   *                                       report.
   * @return list<ArcanistUnitTestResult>  Unit test results.
   */
  public function parseTestResults($path, $output): array {
    if (!strlen($output) || strlen($this->stderr)) {
      $result = id(new ArcanistUnitTestResult())
        ->setName($path)
        ->setResult(ArcanistUnitTestResult::RESULT_BROKEN)
        ->setUserData($this->stderr);
      return [$result];
    }

    // Parse the JUnit test report.
    $parser = new ArcanistXUnitTestResultParser();
    $events = $parser->parseTestResults($output);

    // Coverage is calculated for all test cases in the executed path.
    $coverage = [];
    if ($this->enableCoverage) {
      $coverage_data = Filesystem::readFile($this->coverageFile);
      $coverage = $this->parseCloverCoverage($coverage_data);
    }

    $test_suites = [];
    $results = [];

    // Keep track of how many tests have been executed within each test suite.
    // This is necessary because there is no "suiteEnd" event. Once the count
    // drops to zero, the test suite has finished and can be popped from
    // `$test_suites`.
    $tests_remaining = [];

    // Keep track of the previous test namespace and test name. This allows us
    // to properly attribute a broken test harness to the most-recent test.
    $last_test_namespace = null;
    $last_test_name = null;

    foreach ($events as $event) {
      $event->setCoverage($coverage);
    }

    return $events;
  }


/* -(  Utility  )------------------------------------------------------------ */


  /**
   * Convert "testsuite" and "test" attributes from JUnit JSON output to test
   * namespace and test name.
   *
   * @param  string                The "testsuite" reported by JUnit.
   * @param  string                The "test" reported by JUnit.
   * @return pair<string, string>  The test namespace and test name.
   *
   * @task utility
   */
  public static function getTestName($test_suite, $name) {
    // The test namespace will be the class name. We remove the method name as
    // this is used in the test name.
    $namespace = preg_replace('/::.*$/', '', $test_suite);

    // The test name will be the class method, plus additional information
    // describing the data set (e.g. "with data set #0"). The actual data
    // contained within the data set is removed from the test name.
    $name = preg_replace('/^[^:]+::/', '', $name);
    $name = preg_replace('/ \(.*\)/s', '', $name);

    return [$namespace, $name];
  }

  /**
   * Parse the Clover XML output generated alongside JUnit's output.
   *
   * The return value from this method maps source
   * code paths to a "coverage string", which describes the //line// coverage
   * of the source file. The length of the coverage string is equal to the
   * number of lines contained within the source file. Each character within
   * the coverage string corresponds to a single line of source code according
   * to the following mapping:
   *
   *   - **`C`:** The corresponding source code line is covered.
   *   - **`N`:** Coverage information is not applicable for the corresponding
   *     source code line. This usually indicates that the source code line
   *     consists only of non-semantic tokens such as whitespace or comments.
   *   - **`U`:** The corresponding source code line is uncovered.
   *
   * @param  string               String containing Clover XML output.
   * @return map<string, string>  Coverage data.
   *
   * @task utility
   */
  public function parseCloverCoverage($xml) {
    $dom = new DOMDocument();
    $ok = @$dom->loadXML($xml);

    if ($ok === false) {
      throw new RuntimeException(pht('Unable to parse Clover XML.'));
    }

    $coverage_data = [];
    $files = $dom->getElementsByTagName('file');

    foreach ($files as $file) {
      // Change absolute paths to be relative to the project root directory.
      $file_path = Filesystem::readablePath(
        $file->getAttribute('name'),
        $this->projectRoot);

      $any_line_covered = false;
      $coverage = str_repeat('N', $this->getFileLineCount($file_path));
      $lines = $file->getElementsByTagName('line');

      foreach ($lines as $line) {
        switch ($line->getAttribute('type')) {
          case 'stmt':
            $count = (int)$line->getAttribute('count');

            if ($count > 0) {
              $any_line_covered = true;
              $is_covered = 'C';
            } else if ($count == 0) {
              $is_covered = 'U';
            } else {
              throw new UnexpectedValueException(
                pht(
                  'Unexpected value for "%s" attribute: %d.',
                  'count',
                  $count));
            }

            $line_number = (int)$line->getAttribute('num');
            $coverage[$line_number - 1] = $is_covered;
            break;

          default:
            break;
        }
      }

      // Sometimes the Clover coverage gives false positives on uncovered lines
      // when the file wasn't actually part of the test. This filters out files
      // with no coverage which helps give more accurate overall results.
      if ($any_line_covered) {
        $coverage_data[$file_path] = $coverage;
      }
    }

    return $coverage_data;
  }

}
