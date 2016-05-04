<?php

final class ArcanistCaseInsensitiveNamedConstantXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/case-insensitive-named-constant/');
  }

}
