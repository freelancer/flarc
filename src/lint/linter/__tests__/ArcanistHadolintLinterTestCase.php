<?php

final class ArcanistHadolintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/hadolint/');
  }

}
