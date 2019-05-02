<?php

final class ArcanistForExpressionSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1019;

  public function getLintName() {
    return pht('`%s` Expression Spacing', 'for');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $expressions = $root->selectDescendantsOfType('n_FOR_EXPRESSION');
    foreach ($expressions as $expression) {
      $semicolons = $expression->selectTokensOfType(';');

      foreach ($semicolons as $semicolon) {
        switch ($semicolon->getNextToken()->getTypeName()) {
          case 'T_WHITESPACE':
          case ';':
          case ')':
            continue 2;
        }

        $this->raiseLintAtToken(
          $semicolon,
          pht(
            'Semicolons in `%s` expressions should have a trailing space.',
            'for'),
          '; ');
      }
    }
  }

}
