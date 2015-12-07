<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistDuplicateFunctionDeclarationXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

    const ID = 1005;

    public function getLintName() {
      return pht('Duplicate `%s` Declaration', 'function');
    }

    public function process(XHPASTNode $root) {
      $functions = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
      $declarations = new CaseInsensitiveArray();

      foreach ($functions as $function) {
        // If third child is `n_EMPTY` then it is an anonymous function
        // and can't be a duplicate.
        if ($function->getChildByIndex(2)->getTypeName() == 'n_EMPTY') {
          continue;
        }

        // We don't want to raise a lint error if the declaration is within
        // a conditional block
        $conditional_types = array(
          'n_ELSE',
          'n_ELSE_IF',
          'n_IF',
          'n_SWITCH',
        );

        if ($this->hasAncestorOfTypes($function, $conditional_types)) {
          continue;
        }

        $function_name_node = $function->getChildOfType(2, 'n_STRING');
        $function_name = $function_name_node->getConcreteString();

        if (isset($declarations[$function_name])) {
          // The values from `CaseInsensitiveArray` are returned by value, and
          // not by reference.
          $function_set   = $declarations[$function_name];
          $function_set[] = $function;
          $declarations[$function_name] = $function_set;

          $this->raiseLintAtNode(
            $function_name_node,
            pht(
              'Cannot redeclare function `%s`. '.
              'This will cause a PHP fatal error.',
              $function_name));
          continue;
        }

        $declarations[$function_name] = array($function);
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
     */
    private function hasAncestorOfTypes(XHPASTNode $node, array $types) {
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
