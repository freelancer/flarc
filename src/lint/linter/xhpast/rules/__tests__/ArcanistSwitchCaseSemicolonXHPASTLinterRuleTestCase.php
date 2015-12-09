<?php

final class ArcanistSwitchCaseSemicolonXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/switch-case-semicolon/');
  }

}
