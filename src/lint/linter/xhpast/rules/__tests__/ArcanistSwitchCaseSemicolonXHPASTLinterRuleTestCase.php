<?php

final class ArcanistSwitchCaseSemicolonXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/switch-case-semicolon/');
  }

}
