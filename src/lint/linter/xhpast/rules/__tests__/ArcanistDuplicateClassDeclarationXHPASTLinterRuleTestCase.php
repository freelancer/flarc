<?php

final class ArcanistDuplicateClassDeclarationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/duplicate-class-declaration/');
  }

}
