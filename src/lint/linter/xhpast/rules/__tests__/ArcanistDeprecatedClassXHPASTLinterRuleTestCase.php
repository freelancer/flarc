<?php

final class ArcanistDeprecatedClassXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/deprecated-class/');
  }

}
