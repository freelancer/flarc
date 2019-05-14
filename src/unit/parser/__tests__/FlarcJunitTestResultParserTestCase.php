<?php

final class FlarcJunitTestResultParserTestCase extends PhutilTestCase {

  public function testGetFileLineCount() {
    $parser = new FlarcJunitTestResultParser();

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
    $test_cases = [
      [
        '',
        '',
        [
          '',
          '',
        ],
      ],
      [
        'SomeClassTest',
        'SomeClassTest::testSomeMethod',
        [
          'SomeClassTest',
          'testSomeMethod',
        ],
      ],
      [
        'SomeTest::testB',
        'SomeTest::testB with data set #0 (1, 2, 3)',
        [
          'SomeTest',
          'testB with data set #0',
        ],
      ],
      [
        'SomeTest::testB',
        "SomeTest::testB with data set #1 ('foo', 'bar', 'baz')",
        [
          'SomeTest',
          'testB with data set #1',
        ],
      ],
      [
        'SomeTest::testB',
        'SomeTest::testB with data set #2 (array(), null, stdClass Object ())',
        [
          'SomeTest',
          'testB with data set #2',
        ],
      ],
      [
        'SomeTest::testC',
        'SomeTest::testC with data set "one" (1, 2, 3)',
        [
          'SomeTest',
          'testC with data set "one"',
        ],
      ],
      [
        'SomeTest::testD',
        "SomeTest::testD with data set #3 ('Foo::bar')",
        [
          'SomeTest',
          'testD with data set #3',
        ],
      ],
    ];

    foreach ($test_cases as $test_case) {
      list($test_suite, $test_name, $expected) = $test_case;

      $this->assertEqual(
        $expected,
        FlarcJunitTestResultParser::getTestName(
          $test_suite,
          $test_name));
    }
  }

  public function testParseCloverCoverage() {
    $test_cases = [
      [
        Filesystem::readFile(__DIR__.'/junit-xml/1.xml'),
        [],
      ],
      [
        Filesystem::readFile(__DIR__.'/junit-xml/2.xml'),
        [
          'src/SomeClass.php' => 'NNNNCCNUNNNNNNN',
        ],
      ],
      [
        Filesystem::readFile(__DIR__.'/junit-xml/3.xml'),
        [
          'src/test.ts' => 'NNNNCCNUNNNNNNN',
        ],
      ],
    ];

    foreach ($test_cases as $test_case) {
      list($input, $expected) = $test_case;

      $parser = id(new FlarcJunitTestResultParser())
        ->setAffectedTests(array_fill_keys(array_keys($expected), true));

      foreach ($expected as $path => $coverage_string) {
        $parser->setFileLineCount($path, strlen($coverage_string));
      }

      $this->assertEqual($expected, $parser->parseCloverCoverage($input));
    }
  }

  public function testParseCloverCoverageWithInvalidData() {
    $exception = null;
    $parser = new FlarcJunitTestResultParser();

    try {
      $parser->parseCloverCoverage('');
    } catch (Exception $ex) {
      $exception = $ex;
    }

    $this->assertTrue($exception instanceof RuntimeException);
  }

  public function testParseBrokenTestResults() {
    $expected = [
      id(new ArcanistUnitTestResult())
        ->setName('src/BrokenTest.php')
        ->setResult(ArcanistUnitTestResult::RESULT_BROKEN)
        ->setUserData('Something is broken'),
    ];
    $results = id(new FlarcJunitTestResultParser())
      ->setStderr('Something is broken')
      ->parseTestResults('src/BrokenTest.php', '');

    $this->assertTrue($expected == $results);
  }

}
