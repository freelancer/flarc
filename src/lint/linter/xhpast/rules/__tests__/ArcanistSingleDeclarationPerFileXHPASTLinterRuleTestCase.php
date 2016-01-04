<?php

final class ArcanistSingleDeclarationPerFileXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/single-declaration-per-file/');
  }

}
