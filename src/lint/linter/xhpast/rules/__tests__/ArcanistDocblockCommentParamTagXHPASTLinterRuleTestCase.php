<?php

final class ArcanistDocblockCommentParamTagXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/docblock-comment-param-tag/');
  }

}
