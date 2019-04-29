<?php

final class FlarcPuppetLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/puppet-lint/');
  }

}
