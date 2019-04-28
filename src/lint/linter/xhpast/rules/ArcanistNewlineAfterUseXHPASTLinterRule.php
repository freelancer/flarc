<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistNewlineAfterUseXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1013;

  public function getLintName() {
    return pht('Newline After `%s` Statement', 'use');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $use_lists = $root->selectDescendantsOfType('n_USE_LIST');

    foreach ($use_lists as $use_list) {
      $statement = $use_list->getParentNode();
      $next_statement = $statement->getNextSibling();

      if (!$next_statement) {
        continue;
      }

      // Deal with consecutive `use` statements.
      if ($next_statement && $next_statement->getTypeName() == 'n_STATEMENT') {
        $first_child = $next_statement->getChildByIndex(0);

        if ($first_child && $first_child->getTypeName() == 'n_USE_LIST') {
          if ($next_statement->getLineNumber() ==
              $statement->getLineNumber() + 1) {
            continue;
          }
        }
      }

      list($before, $after) = $statement->getSurroundingNonsemanticTokens();
      $trailing_token = head($after);
      if ($trailing_token &&
        preg_match('/^\s*\n\s*\n/', $trailing_token->getValue())) {

        continue;
      }

      $this->raiseLintAtNode(
        $statement,
        pht(
          '`%s` statements should be separated from code by an empty line.',
          'use'),
        $statement->getConcreteString()."\n");
    }
  }
}
