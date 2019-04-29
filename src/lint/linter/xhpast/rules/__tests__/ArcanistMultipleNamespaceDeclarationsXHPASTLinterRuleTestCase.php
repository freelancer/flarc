<?php

final class ArcanistMultipleNamespaceDeclarationsXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/multiple-namespace-declarations/');
  }

}
