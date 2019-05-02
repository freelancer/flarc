<?php

/**
 * @todo This linter rule could possibly be merged with `ArcanistGlobalVariableXHPASTLinterRule`.
 */
final class ArcanistGlobalsSuperglobalXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1024;

  public function getLintName() {
    return pht('`%s` Superglobal', '$GLOBALS');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $variables = $root->selectDescendantsOfType('n_VARIABLE');

    foreach ($variables as $variable) {
      $name = $this->getConcreteVariableString($variable);

      if ($name != '$GLOBALS') {
        continue;
      }

      $this->raiseLintAtNode(
        $variable,
        pht(
          'The `%s` superglobal variable should not be used.',
          '$GLOBALS'));
    }

    // TODO: This will need to be updated after T28193.
    $strings = $root->selectDescendantsOfType('n_STRING_SCALAR');

    foreach ($strings as $string) {
      $string_variables = $string->getStringVariables();

      foreach ($string_variables as $string_variable) {
        if ($string_variable != 'GLOBALS') {
          continue;
        }

        $this->raiseLintAtNode(
          $string,
          pht(
            'The `%s` superglobal variable should not be used.',
            '$GLOBALS'));
      }
    }
  }

}
