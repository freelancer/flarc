<?php

final class ArcanistForExpressionSpacingXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/for-expression-spacing/');
  }

}
