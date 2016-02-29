<?php

final class ArcanistEmptyDocblockCommentXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/empty-docblock-comment/');
  }

}
