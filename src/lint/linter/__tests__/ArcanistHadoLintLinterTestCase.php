<?php

final class ArcanistHadoLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/hadolint/');
  }

}
