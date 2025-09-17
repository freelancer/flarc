<?php

final class FlarcPhpcsLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/phpcs/');
  }
}
