<?php

final class ArcanistCaseInsensitiveNamedConstantXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/case-insensitive-named-constant/');
  }

}
