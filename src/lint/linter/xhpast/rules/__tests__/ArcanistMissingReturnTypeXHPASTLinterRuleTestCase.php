<?php

final class ArcanistMissingReturnTypeXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/missing-return-type/');
  }

}
