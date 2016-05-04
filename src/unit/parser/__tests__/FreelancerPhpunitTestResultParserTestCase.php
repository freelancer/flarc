<?php

final class FreelancerPhpunitTestResultParserTestCase extends PhutilTestCase {

  public function testGetFileLineCount() {
    $parser = new FreelancerPhpunitTestResultParser();

    // Calculate the number of lines contained within the current file
    // and then compare with the value returned by @{method:getFileLineCount}.
    $contents = Filesystem::readFile(__FILE__);
    $expected = count(phutil_split_lines($contents));
    $this->assertEqual($expected, $parser->getFileLineCount(__FILE__));

    $path = 'some_path';
    $count = 123;
    $parser->setFileLineCount($path, $count);
    $this->assertEqual($count, $parser->getFileLineCount($path));
  }

  public function testGetTestName() {
    $test_cases = array(
      array(
        '',
        '',
        array(
          '',
          '',
        ),
      ),
      array(
        'SomeClassTest',
        'SomeClassTest::testSomeMethod',
        array(
          'SomeClassTest',
          'testSomeMethod',
        ),
      ),
      array(
        'SomeTest::testB',
        'SomeTest::testB with data set #0 (1, 2, 3)',
        array(
          'SomeTest',
          'testB with data set #0',
        ),
      ),
      array(
        'SomeTest::testB',
        "SomeTest::testB with data set #1 ('foo', 'bar', 'baz')",
        array(
          'SomeTest',
          'testB with data set #1',
        ),
      ),
      array(
        'SomeTest::testB',
        'SomeTest::testB with data set #2 (array(), null, stdClass Object ())',
        array(
          'SomeTest',
          'testB with data set #2',
        ),
      ),
      array(
        'SomeTest::testC',
        'SomeTest::testC with data set "one" (1, 2, 3)',
        array(
          'SomeTest',
          'testC with data set "one"',
        ),
      ),
    );

    foreach ($test_cases as $test_case) {
      list($test_suite, $test_name, $expected) = $test_case;

      $this->assertEqual(
        $expected,
        FreelancerPhpunitTestResultParser::getTestName(
          $test_suite,
          $test_name));
    }
  }

  protected function getTestCases() {
    return array(
      array(
        Filesystem::readFile(dirname(__FILE__).'/phpunit-json/1.phpunit-json'),
        array(
          array(
            'event' => 'suiteStart',
            'suite' => 'SomeClassTest',
            'tests' => 1,
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeClassTest',
            'test' => 'SomeClassTest::testSomeMethod',
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeClassTest',
            'test' => 'SomeClassTest::testSomeMethod',
            'status' => 'pass',
            'time' => 0.0087189674377441,
            'trace' => array(),
            'message' => '',
            'output' => '',
          ),
        ),
        array(
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeClassTest')
            ->setName('testSomeMethod')
            ->setResult(ArcanistUnitTestResult::RESULT_PASS)
            ->setDuration(0.0087189674377441)
            ->setCoverage(array()),
        ),
      ),
      array(
        Filesystem::readFile(dirname(__FILE__).'/phpunit-json/2.phpunit-json'),
        array(
          array(
            'event' => 'suiteStart',
            'suite' => 'SomeClassTest',
            'tests' => 1,
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeClassTest',
            'test' => 'SomeClassTest::testSomeMethod',
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeClassTest',
            'test' => 'SomeClassTest::testSomeMethod',
            'status' => 'fail',
            'time' => 0.01228404045105,
            'trace' => array(
              array(
                'file' => 'test/SomeClassTest.php',
                'line' => 13,
                'function' => 'assertTrue',
                'class' => 'PHPUnit_Framework_Assert',
                'type' => '::',
              ),
            ),
            'message' => 'Failed asserting that false is true.',
            'output' => '',
          ),
        ),
        array(
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeClassTest')
            ->setName('testSomeMethod')
            ->setResult(ArcanistUnitTestResult::RESULT_FAIL)
            ->setDuration(0.01228404045105)
            ->setUserData('Failed asserting that false is true.')
            ->setCoverage(array()),
        ),
      ),
      array(
        Filesystem::readFile(dirname(__FILE__).'/phpunit-json/3.phpunit-json'),
        array(
          array(
            'event' => 'suiteStart',
            'suite' => 'SomeClassTest',
            'tests' => 1,
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeClassTest',
            'test' => 'SomeClassTest::testSomeMethod',
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeClassTest',
            'test' => 'SomeClassTest::testSomeMethod',
            'status' => 'error',
            'time' => 0.011281967163086,
            'trace' => array(
              array(
                'file' => 'test/SomeClassTest.php',
                'line' => 13,
              ),
            ),
            'message' => 'Oops!',
            'output' => '',
          ),
        ),
        array(
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeClassTest')
            ->setName('testSomeMethod')
            ->setResult(ArcanistUnitTestResult::RESULT_BROKEN)
            ->setDuration(0.011281967163086)
            ->setUserData('Oops!')
            ->setCoverage(array()),
        ),
      ),
      array(
        Filesystem::readFile(dirname(__FILE__).'/phpunit-json/4.phpunit-json'),
        array(
          array(
            'event' => 'suiteStart',
            'suite' => 'SomeTest',
            'tests' => 8,
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeTest',
            'test' => 'SomeTest::testA',
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeTest',
            'test' => 'SomeTest::testA',
            'status' => 'pass',
            'time' => 0.0018360614776611001,
            'trace' => array(),
            'message' => '',
            'output' => '',
          ),
          array(
            'event' => 'suiteStart',
            'suite' => 'SomeTest::testB',
            'tests' => 3,
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeTest::testB',
            'test' => 'SomeTest::testB with data set #0 (1, 2, 3)',
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeTest::testB',
            'test' => 'SomeTest::testB with data set #0 (1, 2, 3)',
            'status' => 'pass',
            'time' => 0.00080895423889160004,
            'trace' => array(),
            'message' => '',
            'output' => '',
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeTest::testB',
            'test' => "SomeTest::testB with data set #1 ('foo', 'bar', 'baz')",
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeTest::testB',
            'test' => "SomeTest::testB with data set #1 ('foo', 'bar', 'baz')",
            'status' => 'pass',
            'time' => 0.0010159015655518001,
            'trace' => array(),
            'message' => '',
            'output' => '',
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeTest::testB',
            'test' =>
              'SomeTest::testB with data set #2 '.
              '(array(), null, stdClass Object ())',
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeTest::testB',
            'test' =>
              'SomeTest::testB with data set #2 '.
              '(array(), null, stdClass Object ())',
            'status' => 'pass',
            'time' => 0.00069594383239745996,
            'trace' => array(),
            'message' => '',
            'output' => '',
          ),
          array(
            'event' => 'suiteStart',
            'suite' => 'SomeTest::testC',
            'tests' => 3,
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeTest::testC',
            'test' => 'SomeTest::testC with data set "one" (1, 2, 3)',
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeTest::testC',
            'test' => 'SomeTest::testC with data set "one" (1, 2, 3)',
            'status' => 'fail',
            'time' => 0.0013480186462401999,
            'trace' => array(
              array(
                'file' => 'test/SomeTest.php',
                'line' => 35,
                'function' => 'assertLessThan',
                'class' => 'PHPUnit_Framework_Assert',
                'type' => '::',
              ),
            ),
            'message' => 'Failed asserting that 88 is less than 50.',
            'output' => '',
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeTest::testC',
            'test' =>
              "SomeTest::testC with data set \"two\" ".
              "('foo', 'bar', 'baz')",
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeTest::testC',
            'test' =>
              "SomeTest::testC with data set \"two\" ".
              "('foo', 'bar', 'baz')",
            'status' => 'pass',
            'time' => 0.00089812278747558995,
            'trace' => array(),
            'message' => '',
            'output' => '',
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeTest::testC',
            'test' =>
              'SomeTest::testC with data set "three" '.
              '(array(), null, stdClass Object ())',
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeTest::testC',
            'test' =>
              'SomeTest::testC with data set "three" '.
              '(array(), null, stdClass Object ())',
            'status' => 'pass',
            'time' => 0.00078916549682617003,
            'trace' => array(),
            'message' => '',
            'output' => '',
          ),
          array(
            'event' => 'testStart',
            'suite' => '',
            'test' => 'SomeTest::testD',
          ),
          array(
            'event' => 'test',
            'suite' => '',
            'test' => 'SomeTest::testD',
            'status' => 'error',
            'time' => 0.0010561943054199,
            'trace' => array(),
            'message' => 'Skipped Test: Extension some_extension is required.',
            'output' => '',
          ),
        ),
        array(
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeTest')
            ->setName('testA')
            ->setResult(ArcanistUnitTestResult::RESULT_PASS)
            ->setDuration(0.0018360614776611001)
            ->setCoverage(array()),
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeTest')
            ->setName('testB with data set #0')
            ->setResult(ArcanistUnitTestResult::RESULT_PASS)
            ->setDuration(0.00080895423889160004)
            ->setCoverage(array()),
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeTest')
            ->setName('testB with data set #1')
            ->setResult(ArcanistUnitTestResult::RESULT_PASS)
            ->setDuration(0.0010159015655518001)
            ->setCoverage(array()),
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeTest')
            ->setName('testB with data set #2')
            ->setResult(ArcanistUnitTestResult::RESULT_PASS)
            ->setDuration(0.00069594383239745996)
            ->setCoverage(array()),
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeTest')
            ->setName('testC with data set "one"')
            ->setResult(ArcanistUnitTestResult::RESULT_FAIL)
            ->setDuration(0.0013480186462401999)
            ->setUserData('Failed asserting that 88 is less than 50.')
            ->setCoverage(array()),
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeTest')
            ->setName('testC with data set "two"')
            ->setResult(ArcanistUnitTestResult::RESULT_PASS)
            ->setDuration(0.00089812278747558995)
            ->setCoverage(array()),
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeTest')
            ->setName('testC with data set "three"')
            ->setResult(ArcanistUnitTestResult::RESULT_PASS)
            ->setDuration(0.00078916549682617003)
            ->setCoverage(array()),
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeTest')
            ->setName('testD')
            ->setResult(ArcanistUnitTestResult::RESULT_SKIP)
            ->setDuration(0.0010561943054199)
            ->setUserData(
              'Skipped Test: Extension some_extension is required.')
            ->setCoverage(array()),
        ),
      ),
      array(
        Filesystem::readFile(dirname(__FILE__).'/phpunit-json/5.phpunit-json'),
        array(
          array(
            'event' => 'suiteStart',
            'suite' => 'SomeBrokenTest',
            'tests' => 24,
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeBrokenTest',
            'test' => 'SomeBrokenTest::testNotBroken',
          ),
          array(
            'event' => 'test',
            'suite' => 'SomeBrokenTest',
            'test' => 'SomeBrokenTest::testNotBroken',
            'status' => 'pass',
            'time' => 0.014170169830322,
            'trace' => array(),
            'message' => '',
            'output' => '',
          ),
          array(
            'event' => 'testStart',
            'suite' => 'SomeBrokenTest',
            'test' => 'SomeBrokenTest::testBroken',
          ),
        ),
        array(
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeBrokenTest')
            ->setName('testNotBroken')
            ->setResult(ArcanistUnitTestResult::RESULT_PASS)
            ->setDuration(0.014170169830322)
            ->setCoverage(array()),
          id(new ArcanistUnitTestResult())
            ->setNamespace('SomeBrokenTest')
            ->setName('testBroken')
            ->setResult(ArcanistUnitTestResult::RESULT_BROKEN),
        ),
      ),
    );
  }

  public function testParseJsonEvents() {
    $test_cases = $this->getTestCases();

    foreach ($test_cases as $test_case) {
      list($input, $expected) = $test_case;

      $parser = new FreelancerPhpunitTestResultParser();
      $results = $parser->parseJsonEvents($input);

      $this->assertEqual($expected, $parser->parseJsonEvents($input));
    }
  }

  public function testParseJsonEventsWithInvalidData() {
    $exception = null;
    $parser = new FreelancerPhpunitTestResultParser();

    try {
      $parser->parseJsonEvents('');
    } catch (Exception $ex) {
      $exception = $ex;
    }

    $this->assertTrue($exception instanceof RuntimeException);
  }

  public function testParseCloverCoverage() {
    $test_cases = array(
      array(
        Filesystem::readFile(dirname(__FILE__).'/phpunit-xml/1.xml'),
        array(),
      ),
      array(
        Filesystem::readFile(dirname(__FILE__).'/phpunit-xml/2.xml'),
        array(
          'src/SomeClass.php' => 'NNNNCCNUNNNNNNN',
        ),
      ),
    );

    foreach ($test_cases as $test_case) {
      list($input, $expected) = $test_case;

      $parser = id(new FreelancerPhpunitTestResultParser())
        ->setAffectedTests(array_fill_keys(array_keys($expected), true));

      foreach ($expected as $path => $coverage_string) {
        $parser->setFileLineCount($path, strlen($coverage_string));
      }

      $this->assertEqual($expected, $parser->parseCloverCoverage($input));
    }
  }

  public function testParseCloverCoverageWithInvalidData() {
    $exception = null;
    $parser = new FreelancerPhpunitTestResultParser();

    try {
      $parser->parseCloverCoverage('');
    } catch (Exception $ex) {
      $exception = $ex;
    }

    $this->assertTrue($exception instanceof RuntimeException);
  }

  public function testParseTestResults() {
    $test_cases = $this->getTestCases();

    foreach ($test_cases as $test_case) {
      list($input, $_, $expected) = $test_case;

      $parser = new FreelancerPhpunitTestResultParser();
      $this->assertTrue($expected == $parser->parseTestResults('', $input));
    }
  }

  public function testParseBrokenTestResults() {
    $expected = array(
      id(new ArcanistUnitTestResult())
        ->setName('src/BrokenTest.php')
        ->setResult(ArcanistUnitTestResult::RESULT_BROKEN)
        ->setUserData('Something is broken'),
    );
    $results = id(new FreelancerPhpunitTestResultParser())
      ->setStderr('Something is broken')
      ->parseTestResults('src/BrokenTest.php', '');

    $this->assertTrue($expected == $results);
  }

  public function testParseInvalidEventInTestResults() {
    $exception = null;
    $parser = new FreelancerPhpunitTestResultParser();

    try {
      $json = phutil_json_encode(array('event' => 'fake'));
      $parser->parseTestResults('', $json);
    } catch (Exception $ex) {
      $exception = $ex;
    }

    $this->assertTrue($exception instanceof UnexpectedValueException);
  }

  public function testParseInvalidStatusInTestResults() {
    $exception = null;
    $parser = new FreelancerPhpunitTestResultParser();

    try {
      $json = phutil_json_encode(
        array(
          'event' => 'test',
          'test' => 'SomeTest',
          'status' => 'fake',
        ));
      $parser->parseTestResults('', $json);
    } catch (Exception $ex) {
      $exception = $ex;
    }

    $this->assertTrue($exception instanceof UnexpectedValueException);
  }

}
