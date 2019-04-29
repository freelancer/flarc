<?php

final class ArcanistDeprecatedClassXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/deprecated-class/');
  }

}
