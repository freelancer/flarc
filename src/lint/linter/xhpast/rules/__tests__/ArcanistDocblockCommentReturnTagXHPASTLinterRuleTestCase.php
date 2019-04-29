<?php

final class ArcanistDocblockCommentReturnTagXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/docblock-comment-return-tag/');
  }

}
