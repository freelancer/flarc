<?php

final class ArcanistPHPStanLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/phpstan/');
  }
}
