<?php

final class ArcanistBlackLinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/black/');
  }

}
