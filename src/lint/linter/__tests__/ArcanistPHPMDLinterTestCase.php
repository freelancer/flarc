<?php

final class ArcanistPHPMDLinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/phpmd/');
  }

}
