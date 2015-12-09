<?php

final class ArcanistSwitchMultipleDefaultStatementsXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/switch-multiple-defaults/');
  }

}
