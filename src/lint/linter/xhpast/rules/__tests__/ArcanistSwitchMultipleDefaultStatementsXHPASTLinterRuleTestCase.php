<?php

final class ArcanistSwitchMultipleDefaultStatementsXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/switch-multiple-defaults/');
  }

}
