<?php

final class ArcanistTerraformFmtLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/terraform-fmt/');
  }

}
