<?php

final class ArcanistDocblockCommentNewlineXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/docblock-comment-newline/');
  }
}
