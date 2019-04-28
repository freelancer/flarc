<?php

final class ArcanistGeneratorUseReturnXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/generator-use-return/');
  }
}
