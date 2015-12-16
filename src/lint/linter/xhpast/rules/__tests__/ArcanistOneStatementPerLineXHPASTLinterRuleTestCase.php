<?php

final class ArcanistOneStatementPerLineXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/one-statement-per-line/');
  }

}
