<?php

final class ArcanistOneStatementPerLineXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/one-statement-per-line/');
  }

}
