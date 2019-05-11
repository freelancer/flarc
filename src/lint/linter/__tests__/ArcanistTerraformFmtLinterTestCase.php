<?php

final class ArcanistTerraformFmtLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/terraform-fmt/');
  }

}
