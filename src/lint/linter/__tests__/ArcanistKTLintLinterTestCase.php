<?php

final class ArcanistKTLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/ktlint/');
  }

}
