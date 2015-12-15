<?php

final class ArcanistNewlineAfterNamespaceXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/newline-after-namespace/');
  }

}
