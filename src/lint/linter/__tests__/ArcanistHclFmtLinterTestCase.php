<?php

final class ArcanistHclFmtLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/hclfmt/');
  }

}
