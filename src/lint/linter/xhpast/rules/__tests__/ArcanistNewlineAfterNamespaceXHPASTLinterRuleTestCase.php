<?php

final class ArcanistNewlineAfterNamespaceXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/newline-after-namespace/');
  }

}
