<?php

final class ArcanistDuplicatePropertyDeclarationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/duplicate-property-declaration/');
  }

}
