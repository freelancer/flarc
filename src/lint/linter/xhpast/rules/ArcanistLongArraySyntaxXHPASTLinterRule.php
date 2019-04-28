<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistLongArraySyntaxXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1025;

  public function getLintName() {
    return pht('Long Array Syntax');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    if (!$this->version || version_compare($this->version, '5.4.0', '<')) {
      return;
    }

    $arrays = $root->selectDescendantsOfType('n_ARRAY_LITERAL');

    foreach ($arrays as $array) {
      $tokens = $array->getTokens();

      // If the first token is of type `[` then the array is already expressed
      // using the short array syntax.
      if (head($tokens)->getTypeName() == '[') {
        continue;
      }

      // Discard the `T_ARRAY` token.
      $token = array_shift($tokens);
      if ($token->getTypeName() != 'T_ARRAY') {
        throw new Exception(
          pht(
            'Expected token of type `%s` but got `%s`.',
            'T_ARRAY',
            $token->getTypeName()));
      }

      // Discard any whitespace separating the `T_ARRAY` and `(` tokens.
      while (true) {
        $token = head($tokens);

        if ($token->isSemantic()) {
          break;
        }

        array_shift($tokens);
      }

      // Discard the `(` token.
      $token = array_shift($tokens);
      if ($token->getTypeName() != '(') {
        throw new Exception(
          pht(
            'Expected token of type `%s` but got `%s`.',
            '(',
            $token->getTypeName()));
      }

      // Discard the `)` token.
      $token = array_pop($tokens);
      if ($token->getTypeName() != ')') {
        throw new Exception(
          pht(
            'Expected token of type `%s` but got `%s`.',
            ')',
            $token->getTypeName()));
      }

      $short_array = '[';
      foreach ($tokens as $token) {
        $short_array .= $token->getValue();
      }
      $short_array .= ']';

      $this->raiseLintAtNode(
        $array,
        pht(
          'Prefer to use the short array syntax (`%s`) rather than the '.
          'long array syntax (`%s`).',
          '[...]',
          'array(...)'),
        $short_array);
    }
  }
}
