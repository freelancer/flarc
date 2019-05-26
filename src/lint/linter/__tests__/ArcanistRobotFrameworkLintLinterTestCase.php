<?php

final class ArcanistRobotFrameworkLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/rflint/');
  }

}
