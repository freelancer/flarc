<?php

final class ArcanistPHPCSFixerLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/php_cs_fixer/');
  }

}
