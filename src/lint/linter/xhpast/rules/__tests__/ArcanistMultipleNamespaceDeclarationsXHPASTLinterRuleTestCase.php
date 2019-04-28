<?php

final class ArcanistMultipleNamespaceDeclarationsXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/multiple-namespace-declarations/');
  }

}
