<?php

final class ArcanistSingleDeclarationPerFileXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/single-declaration-per-file/');
  }

}
