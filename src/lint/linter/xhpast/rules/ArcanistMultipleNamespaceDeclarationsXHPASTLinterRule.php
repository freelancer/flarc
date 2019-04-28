<?php

final class ArcanistMultipleNamespaceDeclarationsXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1026;

  public function getLintName() {
    return pht('Multiple `%s` declarations', 'namespace');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $namespaces = $root->selectDescendantsOfType('n_NAMESPACE');

    // Skip the first namespace declaration.
    $namespaces = new LimitIterator($namespaces, 1);

    foreach ($namespaces as $namespace) {
      $this->raiseLintAtNode(
        $namespace,
        pht(
          'Each file should declare no more than one `%s`.',
          'namespace'));
    }
  }
}
