<?php

final class ArcanistMissingReturnTypeXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/missing-return-type/');
  }

}
