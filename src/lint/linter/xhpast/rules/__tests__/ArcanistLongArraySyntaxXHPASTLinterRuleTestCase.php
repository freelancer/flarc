<?php

final class ArcanistLongArraySyntaxXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/long-array-syntax/');
  }

}
