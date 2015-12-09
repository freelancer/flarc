<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistSwitchMultipleDefaultStatementsXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1011;

  public function getLintName() {
    return pht(
      'Multiple `%s` Blocks Within `%s` Statement',
      'default',
      'switch');
  }

  public function process(XHPASTNode $root) {
    $switches = $root->selectDescendantsOfType('n_SWITCH');

    foreach ($switches as $switch) {
      $statements = $switch->getChildOfType(1, 'n_STATEMENT_LIST');
      $defaults   = array();

      foreach ($statements->getChildren() as $statement) {
        if ($statement->getTypeName() != 'n_DEFAULT') {
          continue;
        }

        if ($defaults) {
          $this->raiseLintAtNode(
            $statement,
            pht(
              'A `%s` statement should only have one `%s` block.',
              'switch',
              'default'));
        }

        $defaults[] = $statement;
      }
    }
  }

}
