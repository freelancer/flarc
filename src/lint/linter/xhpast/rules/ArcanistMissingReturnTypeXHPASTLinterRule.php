<?php

final class ArcanistMissingReturnTypeXHPASTLinterRule
  extends FlarcXHPASTLinterRule {

  const ID = 2004;

  public function getLintName() {
    return pht('Missing Return Type Declaration');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    if (version_compare($this->version, '7.0.0', '<')) {
      return;
    }

    $nodes = $root->selectDescendantsOfTypes([
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ]);

    foreach ($nodes as $node) {
      // Return type is not empty
      $return_type_node = $node->getChildbyIndex(5);
      if ($return_type_node->getTypeName() !== 'n_EMPTY') {
        continue;
      }
      if ($this->functionHasReturnValue($node) === false) {
        continue;
      }
      $return_tag = $this->getDocblockTag($node, 'return');
      if (is_array($return_tag)) {
        $this->raiseLintAtToken(
          $node->getDocblockToken(),
          pht('Multiple `%s` tags in the docblock comment', '@return'));
        continue;
      } else if ($return_tag === null) {
        $return_tag = '';
      }
      $return_tag_type = head(explode(' ', $return_tag));
      if ($this->isNullableDocblockType($return_tag_type)) {
          $this->raiseLintAtNode(
            $node,
            pht(
              'Nullable return type declaration is missing from the '.
              'function/method declaration'));
      } else if (!$this->isUnionDocblockType($return_tag_type)) {
        $this->raiseLintAtNode(
          $node,
          pht(
            'Return type is missing for this function/method. If you cannot '.
            'declare the return type because the returned value could be one '.
            'of multiple types, you should document it using the `%s` or `%s` '.
            'syntax in the docblock.',
            '@return type1|type2',
            '@return mixed'));
      }
    }
  }

  /**
   * Whether a function or method returns value.
   *
   * @param  XHPASTNode  A 'n_METHOD_DECLARATION' or `n_FUNCTION_DECLARATION` node.
   * @return bool
   */
  private function functionHasReturnValue(XHPASTNode $function) {
    $closures = $this->getAnonymousClosures($function);
    $nodes = $function->selectDescendantsOfTypes(['n_RETURN', 'n_YIELD']);
    foreach ($nodes as $node) {
      foreach ($closures as $closure) {
        if ($node->isDescendantOf($closure)) {
          continue 2;
        }
      }
      if ($node->getChildByIndex(0)->getTypeName() === 'n_EMPTY') {
        continue;
      }
      return true;
    }
    return false;
  }
}
