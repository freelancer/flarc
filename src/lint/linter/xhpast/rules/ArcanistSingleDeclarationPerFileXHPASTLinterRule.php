<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistSingleDeclarationPerFileXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1015;

  public function getLintName() {
    return pht('Single Declaration Per File');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $declarations = $root->selectDescendantsOfTypes([
      'n_CLASS_DECLARATION',
      'n_INTERFACE_DECLARATION',

      // This doesn't actually exist yet, but it's listed here for
      // forwards-compatibility. See T28174.
      // 'n_TRAIT_DECLARATION',
    ]);

    if (count($declarations) <= 1) {
      return;
    }

    // Don't raise a linter message at the first declaration. We need to
    // convert the `AASTNodeList` to an array first, otherwise `array_shift`
    // pops an unexpected element from the object.
    $declarations = $declarations->getRawNodes();
    array_shift($declarations);

    foreach ($declarations as $declaration) {
      $this->raiseLintAtNode(
        $declaration,
        pht(
          'Each file should declare no more than one '.
          '`%s`, `%s` or `%s` declaration.',
          'class',
          'interface',
          'trait'));
    }
  }

}
