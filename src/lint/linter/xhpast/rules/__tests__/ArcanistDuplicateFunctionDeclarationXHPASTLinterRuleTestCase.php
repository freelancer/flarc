<?php

final class ArcanistDuplicateFunctionDeclarationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/duplicate-function-declaration/');
  }

}
