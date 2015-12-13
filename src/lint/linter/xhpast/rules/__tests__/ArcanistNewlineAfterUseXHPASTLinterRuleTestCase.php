<?php

final class ArcanistNewlineAfterUseXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/newline-after-use/');
  }

}
