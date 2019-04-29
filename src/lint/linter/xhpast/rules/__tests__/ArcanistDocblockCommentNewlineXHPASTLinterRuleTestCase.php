<?php

final class ArcanistDocblockCommentNewlineXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/docblock-comment-newline/');
  }
}
