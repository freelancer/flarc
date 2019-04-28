<?php

final class ArcanistGlobalsSuperglobalXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/globals-superglobal/');
  }

}
