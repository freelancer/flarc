<?php

final class ArcanistNewlineAfterUseXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/newline-after-use/');
  }

}
