<?php

final class FlarcPuppetLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/puppet-lint/');
  }

}
