<?php

final class ArcanistDuplicateParameterNameXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/duplicate-parameter-name/');
  }
}
