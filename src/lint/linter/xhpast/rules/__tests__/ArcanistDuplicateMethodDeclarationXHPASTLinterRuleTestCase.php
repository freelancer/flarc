<?php

final class ArcanistDuplicateMethodDeclarationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/duplicate-method-declaration/');
  }

}
