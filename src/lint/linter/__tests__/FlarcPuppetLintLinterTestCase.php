<?php

final class FlarcPuppetLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/puppet-lint/');
  }

}
