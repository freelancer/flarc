<?php

final class ArcanistDuplicateFunctionDeclarationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/duplicate-function-declaration/');
  }

}
