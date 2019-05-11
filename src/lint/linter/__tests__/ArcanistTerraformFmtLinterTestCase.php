<?php

final class ArcanistTerraformFmtLinterTestCase
  extends ArcanistExternalLinterTestCase {

  /**
   * TODO: These tests fail on Terraform 0.12.
   */
  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/terraform-fmt/');
  }

}
