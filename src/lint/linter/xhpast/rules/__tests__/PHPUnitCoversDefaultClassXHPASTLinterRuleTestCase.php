<?php

final class PHPUnitCoversDefaultClassXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/phpunit-covers-default-class/');
  }

}
