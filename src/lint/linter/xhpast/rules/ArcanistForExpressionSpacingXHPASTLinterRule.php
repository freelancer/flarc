<?php

final class ArcanistForExpressionSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1019;

  public function getLintName() {
    return pht('For Expression Spacing.');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $expressions = $root->selectDescendantsOfType('n_FOR_EXPRESSION');
    foreach ($expressions as $expression) {
      $semicolons = $expression->selectTokensOfType(';');

      foreach ($semicolons as $semicolon) {
        if ($semicolon->getNextToken()->getTypeName() == 'T_WHITESPACE' ||
            $semicolon->getNextToken()->getTypeName() == ';' ||
            $semicolon->getNextToken()->getTypeName() == ')') {
          continue;
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
