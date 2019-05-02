<?php

final class ArcanistSwitchCaseSemicolonXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1010;

  public function getLintName() {
    return pht('`%s` Succeeded By Semicolon', 'case');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $cases = $root->selectDescendantsOfTypes(['n_CASE', 'n_DEFAULT']);

    foreach ($cases as $case) {
      switch ($case->getTypeName()) {
        case 'n_CASE':
          $statements = $case->getChildOfType(1, 'n_STATEMENT_LIST');
          break;

        case 'n_DEFAULT':
          $statements = $case->getChildOfType(0, 'n_STATEMENT_LIST');
          break;

        default:
          throw new Exception(
            pht(
              'Unexpected node of type "%s"',
              $case->getTypeName()));
      }
      $statement_tokens = $statements->getTokens();

      if (!$statement_tokens) {
        continue;
      }

      // The first semantic previous token should be either `:` or `;`.
      $colon = head($statement_tokens)->getPrevToken();
      while (!$colon->isSemantic()) {
        $colon = $colon->getPrevToken();
      }

      if ($colon->getTypeName() == ';') {
        $this->raiseLintAtToken(
          $colon,
          pht(
            'Use `%s` instead of `%s` after a `%s` statement.',
            ':',
            ';',
            'case'),
          ':');
      }
    }
  }

}
