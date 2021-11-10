<?php

/**
 * Test for @{class:ArcanistXUnitTestResultParser}.
 */
final class FlarcXUnitTestResultParserTestCase extends PhutilTestCase {

  public function testAcceptsNoTestsInput() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/junit-xml/xunit.no-tests');
    $parsed_results = id(new FlarcXUnitTestResultParser())
      ->parseTestResults($stubbed_results);

    $this->assertEqual(0, count($parsed_results));
  }

  public function testAcceptsSimpleInput() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/junit-xml/xunit.simple');
    $parsed_results = id(new FlarcXUnitTestResultParser())
      ->parseTestResults($stubbed_results);

    $this->assertEqual($parsed_results[0]->getNamespace(), 'forpytest.tests');
    $this->assertEqual($parsed_results[0]->getName(), 'forpytest.tests.test_answer');

    $this->assertEqual(3, count($parsed_results));
  }

  public function testEmptyInputFailure() {
    try {
      $parsed_results = id(new FlarcXUnitTestResultParser())
        ->parseTestResults('');

      $this->assertFailure(pht('Should throw on empty input'));
    } catch (Throwable $e) {
      $this->assertEqual($e->getMessage(), pht(
        '%s argument to %s must not be empty',
        'test_results',
        'parseTestResults()'));
    }
  }

  public function testInvalidXmlInputFailure() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/junit-xml/xunit.invalid-xml');
    try {
      $parsed_results = id(new FlarcXUnitTestResultParser())
        ->parseTestResults($stubbed_results);

      $this->assertFailure(pht('Should throw on non-xml input'));
    } catch (Throwable $e) {
      $this->assertTrue(strpos($e->getMessage(), 'Failed to load XUnit report; Input starts with:') !== false);
    }
  }

}
