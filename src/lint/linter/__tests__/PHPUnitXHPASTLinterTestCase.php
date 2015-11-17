<?php

final class PHPUnitXHPASTLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $linter = new ArcanistXHPASTLinter();
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/xhpast/',
      $linter);
  }

}
