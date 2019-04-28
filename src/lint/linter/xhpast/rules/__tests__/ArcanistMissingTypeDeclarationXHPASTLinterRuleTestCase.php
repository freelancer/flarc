<?php

final class ArcanistMissingTypeDeclarationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/missing-type-declaration/');
  }
}
