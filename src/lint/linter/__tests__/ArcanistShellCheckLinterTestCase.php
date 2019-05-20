<?php

final class ArcanistShellCheckLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/shellcheck/');
  }

}
