<?php

final class ArcanistYAMLLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/yamllint/');
  }

}
