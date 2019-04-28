<?php

final class ArcanistEmptyListAssignmentXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1031;

  public function getLintName() {
    return pht('Use of empty `%s` assignments', 'list');
  }

  public function process(XHPASTNode $root) {
    $assignment_lists = $root->selectDescendantsOfType('n_ASSIGNMENT_LIST');
    foreach ($assignment_lists as $assignment_list) {
      $assignments = $assignment_list->getChildren();

      foreach ($assignments as $assignment) {
        if ($assignment->getTypeName() != 'n_EMPTY') {
          continue 2;
        }
      }
      $this->raiseLintAtNode(
        $assignment_list,
        pht(
          'Use of empty `%s` assignments is discouraged because it will'.
          ' cause fatal error since PHP 7.0',
          'list'));
    }
  }
}
