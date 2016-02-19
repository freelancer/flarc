<?php

final class ArcanistESLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/eslint/');
  }

}
