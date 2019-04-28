<?php

final class ArcanistEmptyListAssignmentXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/empty-list-assignment/');
  }
}
