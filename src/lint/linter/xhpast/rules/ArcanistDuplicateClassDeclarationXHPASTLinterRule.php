<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistDuplicateClassDeclarationXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1016;

  public function getLintName() {
    return pht('Duplicate `%s` Declaration', 'class');
  }

  public function process(XHPASTNode $root) {
    $symbols = $root->selectDescendantsOfTypes(array(
      'n_CLASS_DECLARATION',
      'n_INTERFACE_DECLARATION',

      // This doesn't actually exist yet, but it's listed here for
      // forwards-compatibility. See T28174.
      // 'n_TRAIT_DECLARATION',
    ));
    $declarations = new CaseInsensitiveArray();

    foreach ($symbols as $symbol) {
      // We don't want to raise a lint error if the declaration is within a
      // conditional block.
      static $conditional_types = array(
        'n_ELSE',
        'n_ELSE_IF',
        'n_IF',
        'n_SWITCH',
      );

      if ($this->hasAncestorOfTypes($symbol, $conditional_types)) {
        continue;
      }

      $symbol_name_node = $symbol->getChildOfType(1, 'n_CLASS_NAME');
      $symbol_name = $symbol_name_node->getConcreteString();

      if (isset($declarations[$symbol_name])) {
        // The values from `CaseInsensitiveArray` are returned by value, and
        // not by reference.
        $symbol_set   = $declarations[$symbol_name];
        $symbol_set[] = $symbol;
        $declarations[$symbol_name] = $symbol_set;

        $this->raiseLintAtNode(
          $symbol_name_node,
          pht(
            'Cannot redeclare symbol `%s`. This will cause a PHP fatal error.',
            $symbol_name));
        continue;
      }

      $declarations[$symbol_name] = array($symbol);
    }
  }

  /**
   * This function returns whether or not a node has any ancestors of the
   * specified types.
   *
   * @param  XHPASTNode    Input node.
   * @param  list<string>  An array of types to search for as ancestors.
   * @return bool          `true` if any of the specified types are ancestors
   *                       of the input node, otherwise `false`.
   *
   * @todo This method is copied from
   * @{class:ArcanistDuplicateFunctionDeclarationXHPASTLinterRule} and should
   * be moved to a parent class.
   */
  protected function hasAncestorOfTypes(XHPASTNode $node, array $types) {
    $types = array_fill_keys($types, true);
    $current = $node->getParentNode();

    while ($current != null) {
      if (idx($types, $current->getTypeName())) {
          return true;
      }

      $current = $current->getParentNode();
    }

    return false;
  }

}
