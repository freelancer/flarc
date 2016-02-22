<?php

final class ArcanistUnknownDocblockCommentTagXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/unknown-docblock-comment-tag/');
  }

}
