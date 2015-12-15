<?php

final class ArcanistDuplicateMethodDeclarationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/duplicate-method-declaration/');
  }

}
