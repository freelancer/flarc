<?php

final class ArcanistGeneratorUseReturnXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/generator-use-return/');
  }
}
