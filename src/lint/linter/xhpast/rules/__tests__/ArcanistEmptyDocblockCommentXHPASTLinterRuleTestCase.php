<?php

final class ArcanistEmptyDocblockCommentXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/empty-docblock-comment/');
  }

}
