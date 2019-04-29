<?php

final class ArcanistGlobalsSuperglobalXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/globals-superglobal/');
  }

}
