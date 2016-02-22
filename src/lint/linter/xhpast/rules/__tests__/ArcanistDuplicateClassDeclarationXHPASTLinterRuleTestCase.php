<?php

final class ArcanistDuplicateClassDeclarationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/duplicate-class-declaration/');
  }

}
