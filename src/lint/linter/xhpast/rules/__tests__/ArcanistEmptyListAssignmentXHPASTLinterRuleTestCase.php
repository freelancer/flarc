<?php

final class ArcanistEmptyListAssignmentXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/empty-list-assignment/');
  }
}
