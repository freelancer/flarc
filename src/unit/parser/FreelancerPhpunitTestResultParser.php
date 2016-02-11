<?php

/**
 * Test result parser for PHPUnit.
 *
 * This class parses PHPUnit test results produced with the `--log-json` flag
 * and Clover XML coverage data produced with the `--coverage-clover` flag.
 * This class is based on @{class:ArcanistPhpunitTestResultParser}.
 *
 * @task config   Configuration
 * @task parser   Test Result Parsing
 * @task utility  Utility
 */
final class FreelancerPhpunitTestResultParser extends ArcanistTestResultParser {

  private $fileLineCounts = array();


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
   * Parse test results from the PHPUnit JSON report.
   *
   * This method converts output from PHPUnit (produced with the `--log-json`
   * and `--coverage-clover` flags) to instances of
   * @{class:ArcanistUnitTestResult}.
   *
   * @param  string                        Path of the test file.
   * @param  string                        String containing the PHPUnit JSON
   *                                       report.
   * @return list<ArcanistUnitTestResult>  Unit test results.
   */
  public function parseTestResults($path, $output) {
    if (!$output) {
      $result = id(new ArcanistUnitTestResult())
        ->setName($path)
        ->setResult(ArcanistUnitTestResult::RESULT_BROKEN)
        ->setUserData($this->stderr);
      return array($result);
    }

    // Parse the JSON test report.
    $events = self::parseJsonEvents($output);

    // Coverage is calculated for all test cases in the executed path.
    $coverage = array();
    if ($this->enableCoverage) {
      $coverage_data = Filesystem::readFile($this->coverageFile);
      $coverage = $this->parseCloverCoverage($coverage_data);
    }

    $test_suites = array();
    $results = array();

    // Keep track of how many tests have been executed within each test suite.
    // This is necessary because there is no "suiteEnd" event. Once the count
    // drops to zero, the test suite has finished and can be popped from
    // `$test_suites`.
    $tests_remaining = array();

    // Keep track of the previous test namespace and test name. This allows us
    // to properly attribute a broken test harness to the most-recent test.
    $last_test_namespace = null;
    $last_test_name = null;

    foreach ($events as $event) {
      $result = new ArcanistUnitTestResult();

      switch ($event['event']) {
        case 'suiteStart':
          // TODO: We should maybe do a sanity check here to ensure that
          // `$event['tests'] < last($tests_remaining)`. We should also
          // probably check that `$event['tests']` is actually an integer and
          // that the value is positive.
          $test_suites[] = $event['suite'];
          $tests_remaining[] = $event['tests'];
          continue 2;

        case 'test':
          list($namespace, $name) = self::getTestName(
            last($test_suites),
            $event['test']);
          $result->setNamespace($namespace);
          $result->setName($name);

          // Keep track of the number of tests remaining in each test suite.
          for ($i = 0; $i < count($tests_remaining); $i++) {
            $tests_remaining[$i]--;
          }
          while (last($tests_remaining) === 0) {
            array_pop($test_suites);
            array_pop($tests_remaining);
          }

          break;

        case 'testStart':
          list($last_test_namespace, $last_test_name) = self::getTestName(
            $event['suite'],
            $event['test']);
          continue 2;

        default:
          throw new UnexpectedValueException(
            pht(
              'Unexpected event in PHPUnit JSON report: "%s"',
              $event['event']));
      }

      $message = idx($event, 'message');

      switch ($event['status']) {
        case 'error':
          if (strpos($message, 'Skipped Test: ') !== false) {
            $result->setResult(ArcanistUnitTestResult::RESULT_SKIP);
          } else if (strpos($message, 'Incomplete Test: ') !== false) {
            $result->setResult(ArcanistUnitTestResult::RESULT_SKIP);
          } else {
            $result->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
          }
          $result->setUserData($message);
          break;

        case 'fail':
          $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
          $result->setUserData($message);
          break;

        case 'pass':
          $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
          break;

        default:
          throw new UnexpectedValueException(
            pht(
              "Unexpected status in PHPUnit JSON report: '%s'",
              $event['status']));
      }

      $result->setCoverage($coverage);
      $result->setDuration($event['time']);
      $results[] = $result;
    }

    // If the `$tests_remaining` array is non-empty, something has gone wrong.
    // This can happen if a fatal error occurs during test execution, in which
    // case the JSON output from PHPUnit will be incomplete.
    if ($tests_remaining) {
      $results[] = id(new ArcanistUnitTestResult())
        ->setNamespace($last_test_namespace)
        ->setName($last_test_name)
        ->setResult(ArcanistUnitTestResult::RESULT_BROKEN)
        ->setUserData($this->stderr);
    }

    return $results;
  }


/* -(  Utility  )------------------------------------------------------------ */


  /**
   * Convert "testsuite" and "test" attributes from PHPUnit JSON output to test
   * namespace and test name.
   *
   * @param  string                The "testsuite" reported by PHPUnit.
   * @param  string                The "test" reported by PHPUnit.
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
    $name = preg_replace('/^.*::/', '', $name);
    $name = preg_replace('/ \(.*\)/s', '', $name);

    return array($namespace, $name);
  }

  /**
   * Parse the Clover XML output.
   *
   * Parses the Clover coverage XML output produced by PHPUnit with the
   * `--coverage-clover` flag. The return value from this method maps source
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

    $coverage_data = array();
    $files = $dom->getElementsByTagName('file');

    foreach ($files as $file) {
      // Change absolute paths to be relative to the project root directory.
      $file_path = Filesystem::readablePath(
        $file->getAttribute('name'),
        $this->projectRoot);

      $coverage = '';
      $lines = $file->getElementsByTagName('line');
      $start_line = 1;

      foreach ($lines as $line) {
        $line_number = (int)$line->getAttribute('num');

        for (; $start_line < $line_number; $start_line++) {
          $coverage .= 'N';
        }

        switch ($line->getAttribute('type')) {
          case 'stmt':
            $count = (int)$line->getAttribute('count');

            if ($count == 0) {
              $coverage .= 'U';
            } else if ($count > 0) {
              $coverage .= 'C';
            } else {
              throw new UnexpectedValueException(
                pht(
                  'Unexpected value for "%s" attribute: %d.',
                  'count',
                  $count));
            }
            break;

          default:
            $coverage .= 'N';
            break;
        }

        $start_line++;
      }

      $line_count = $this->getFileLineCount($file_path);
      for (; $start_line <= $line_count; $start_line++) {
        $coverage .= 'N';
      }

      $coverage_data[$file_path] = $coverage;
    }

    return $coverage_data;
  }

  /**
   * Parse the PHPUnit JSON output.
   *
   * Passing `--log-json` to PHPUnit causes it to output invalid JSON, see
   * [[https://github.com/sebastianbergmann/phpunit/issues/143 | PHPUnit
   * doesn't log valid JSON]]. This method converts "PHPUnit JSON" to regular
   * JSON and returns the parsed JSON object.
   *
   * @param  string  String containing JSON report.
   * @return array   Decoded JSON data.
   */
  public function parseJsonEvents($json) {
    if (!$json) {
      throw new RuntimeException(
        pht(
          'JSON report file is empty, which probably means that PHPUnit '.
          'failed to run tests. Try running `%s` with `%s` option and then '.
          'run the generated PHPUnit command yourself.',
          'arc unit',
          '--trace'));
    }

    return phutil_json_decode('['.preg_replace('/}{\s*"/', '},{"', $json).']');
  }

}
