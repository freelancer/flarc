<?php

/**
 * @todo Submit this upstream after T27678. See
 * https://phabricator.freelancer.com/differential/diff/593341/.
 */
final class ArcanistCommaSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1020;

  public function getLintName() {
    return pht('Spacing Around Commas');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $tokens = $root->selectTokensOfType(',');
    foreach ($tokens as $token) {
      $next = $token->getNextToken();
      switch ($next->getTypeName()) {
        case ')':
        case 'T_WHITESPACE':
          break;
        default:
          $this->raiseLintAtToken(
            $token,
            pht('Convention: comma should be followed by space.'),
            ', ');
          break;
      }

      $prev = $token->getPrevToken();
      if ($prev->getTypeName() == 'T_WHITESPACE') {
        // If the token before the whitespace preceding the comma is on a
        // different line to the comma itself we want to move the comma to be
        // on the correct line and maintain the multiline indentation.
        $pre_whitespace = $prev->getPrevToken();
        if ($pre_whitespace->getLineNumber() != $token->getLineNumber()) {
          // Heredocs have a very specific syntax that involves ','.
          // This case is here to ensure the rule doesn't mess with that.
          if ($pre_whitespace->getTypeName() == 'T_HEREDOC') {
            continue;
          }

          $old = $prev->getValue().$token->getValue();
          if ($next->getTypeName() == 'T_WHITESPACE') {
            $old .= $next->getValue();
          }

          $this->raiseLintAtOffset(
            $prev->getOffset(),
            pht('Convention: comma should not be preceded by space.'),
            $old,
            ','.$prev->getValue());
        } else {
          $this->raiseLintAtToken(
            $prev,
            pht('Convention: comma should not be preceded by space.'),
            '');
        }
      }
    }

  }

}
