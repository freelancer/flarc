<?php

final class PHPUnitCoversDefaultClassXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/phpunit-covers-default-class/');
  }

}
