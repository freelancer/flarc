<?php

final class ArcanistDocblockCommentParamTagXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/docblock-comment-param-tag/');
  }

}
