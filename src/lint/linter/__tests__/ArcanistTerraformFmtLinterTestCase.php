<?php

final class ArcanistTerraformFmtLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/terraform-fmt/');
  }

}
