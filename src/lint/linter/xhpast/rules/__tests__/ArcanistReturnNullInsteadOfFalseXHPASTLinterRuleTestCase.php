<?php

final class ArcanistReturnNullInsteadOfFalseXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/use-nullable-type/');
  }
}
