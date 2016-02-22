<?php

final class ArcanistDuplicatePropertyDeclarationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/duplicate-property-declaration/');
  }

}
