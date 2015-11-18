<?php

final class PHPUnitXHPASTLinterTestCase extends ArcanistLinterTestCase {

  protected function getLinter() {
    return new ArcanistXHPASTLinter();
  }

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/xhpast/');
  }

}
