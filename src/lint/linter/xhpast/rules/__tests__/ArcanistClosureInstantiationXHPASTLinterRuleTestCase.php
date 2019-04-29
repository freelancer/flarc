<?php

final class ArcanistClosureInstantiationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/closure-instantiation/');
  }

}
