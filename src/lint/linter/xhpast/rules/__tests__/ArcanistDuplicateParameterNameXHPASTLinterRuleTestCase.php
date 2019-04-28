<?php

final class ArcanistDuplicateParameterNameXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/duplicate-parameter-name/');
  }
}
