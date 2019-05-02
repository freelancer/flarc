<?php

final class ArcanistNewlineAfterNamespaceXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1007;

  public function getLintName() {
    return pht('Newline After `%s` Declaration', 'namespace');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $namespaces = $root->selectDescendantsOfType('n_NAMESPACE');

    foreach ($namespaces as $namespace) {
      // Get statement node above namespace node to include `;`.
      $statement = $namespace->getParentNode();
      list($before, $after) = $statement->getSurroundingNonsemanticTokens();

      // Only the head is checked since the first token after the statement
      // should be `T_WHITESPACE`, any comment would be picked up by other
      // rules.
      if (preg_match('/^\n\s*\n/', head($after)->getValue())) {
        continue;
      }

      $this->raiseLintAtNode(
        $statement,
        pht(
          '`%s` declarations should be separated from code by an empty line.',
          'namespace'),
        $statement->getConcreteString()."\n");
    }
  }

}
