<?php

final class ArcanistLongArraySyntaxXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/long-array-syntax/');
  }

}
