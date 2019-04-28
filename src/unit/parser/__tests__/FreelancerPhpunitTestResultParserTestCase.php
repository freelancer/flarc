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
      array(
        'SomeTest::testD',
        "SomeTest::testD with data set #3 ('Foo::bar')",
        array(
          'SomeTest',
          'testD with data set #3',
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

}
