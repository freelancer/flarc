<?php

final class ArcanistUnknownDocblockCommentTagXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      __DIR__.'/unknown-docblock-comment-tag/');
  }

}
