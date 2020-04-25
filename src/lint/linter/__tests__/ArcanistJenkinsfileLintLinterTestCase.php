<?php

final class ArcanistJenkinsfileLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/jenkinsfile/');
  }
}
