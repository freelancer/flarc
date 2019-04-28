<?php

final class ArcanistMissingTypeAnnotationXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/missing-type-annotation/');
  }

}
