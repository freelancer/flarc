<?php

final class ArcanistDocblockCommentReturnTagXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/docblock-comment-return-tag/');
  }

}
