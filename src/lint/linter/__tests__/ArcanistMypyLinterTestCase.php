<?php

final class ArcanistMypyLinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/mypy/');
  }

}
