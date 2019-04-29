<?php

final class ArcanistDocblockCommentParamTagXHPASTLinterRule
  extends FlarcXHPASTLinterRule {

  const ID = 2001;

  public function getLintName() {
    return pht(
      '`%s` Tag Incompatible with Function/Method Declaration.',
      '@param');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $functions = $root->selectDescendantsOfTypes([
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ]);

    foreach ($functions as $function) {
      $docblock = $function->getDocblockToken();

      if (!$docblock) {
        continue;
      }

      // The parameters for methods with a `@dataProvider` tag should be
      // documented in corresponding data provider method.
      list($_, $_, $tags) = $this->parseDocblock($docblock);
      if (
        $function->getTypeName() == 'n_METHOD_DECLARATION' &&
        isset($tags['dataProvider'])) {

        continue;
      }

      $docblock_types = $this->getParameterTypesFromDocblock($docblock);
      $declared_types = $this->getParameterTypesFromDeclaration($function);

      // Check variadic arguments
      if (!empty($docblock_types) && end($docblock_types) === '...') {
        $calls = $this->getFunctionCalls(
          $function,
          ['func_get_arg', 'func_get_args']);

        if ($calls->count() == 0) {
          $this->raiseLintAtToken(
            $docblock,
            pht(
              'Docblock indicates the function/method use variadic arguments,'.
              ' but neither `%s` nor `%s` is called',
              'func_get_arg',
              'func_get_args'));
        }
        array_pop($docblock_types);
      }

      if (count($docblock_types) != count($declared_types)) {
        $this->raiseLintAtToken(
          $docblock,
          pht(
            'Docblock has %s `%s` tags, but %s parameters declared.',
            phutil_count($docblock_types),
            '@param',
            phutil_count($declared_types)));
        continue;
      }

      $n_params = count($declared_types);
      for ($ii = 0; $ii < $n_params; $ii++) {
        // No type hint on this parameter, so we cannot check
        // the correctness of this `@param` tag.
        if ($declared_types[$ii] == null) {
          continue;
        }
        try {
          if (!$this->isCompatibleDocBlockType(
            $docblock_types[$ii],
            $declared_types[$ii])) {

            $this->raiseLintAtToken(
              $docblock,
              pht(
                'The type of parameter %d was `%s` in docblock, '.
                'but declared as `%s`',
                $ii,
                $docblock_types[$ii],
                $declared_types[$ii]));
          }
        } catch (Exception $e) {
          $this->raiseLintAtToken(
            $docblock,
            pht(
              'Invalid docblock parameter type `%s`',
              $docblock_types[$ii]));
        }
      }
    }
  }

  /**
   * Get datatype behind `@param` tags.
   *
   * For example, if we process this docblock, we should get
   * ['XHPASTToken'] as this is the only `@param` tag type in this
   * docblock. (See more in
   * [[https://phpdoc.org/docs/latest/guides/types.html | types]])
   *
   * @param  XHPASTToken  Docblock token for a function or method.
   * @return list<string> Datatypes.
   */
  private function getParameterTypesFromDocblock(XHPASTToken $docblock) {
    list($_, $_, $tags) = $this->parseDocblock($docblock);

    if (!isset($tags['param'])) {
      return [];
    }

    $types = [];
    if (!is_array($tags['param'])) {
      $tags['param'] = [$tags['param']];
    }
    foreach ($tags['param'] as $line) {
      $types[] = head(explode(' ', $line));
    }

    return $types;
  }

  /**
   * Get type hints in parameter declaration list.
   *
   * For example, in
   * ```
   * function f (array $a, XHPASTNode $node, $s) {...}
   * ```
   * The type hints are ['array', 'XHPASTNode', null].
   *
   * @param  XHPASTNode   Function node or method node.
   * @return list<string> Type hints
   */
  private function getParameterTypesFromDeclaration(XHPASTNode $function): array {

    // Retrieve declared parameters from first declaration list.
    $parameter_list = $function->getChildOfType(
      3, 'n_DECLARATION_PARAMETER_LIST');
    $parameters = $parameter_list->getChildrenOfType(
      'n_DECLARATION_PARAMETER');

    $types = [];
    foreach ($parameters as $parameter) {
      $type_node = $parameter->getChildByIndex(0);

      if ($type_node->getTypeName() === 'n_EMPTY') {
        $types[] = null;
        continue;
      }

      // Qualified names may consist of more than one token, so we need
      // to concatenate them together.
      $type_hint = '';
      foreach ($type_node->getTokens() as $token) {
        if (!$token->isSemantic()) {
          break;
        }
        $type_hint .= $token->getValue();
      }

      $default_value_node = $parameter->getChildByIndex(2);
      if ($type_node->getTypeName() === 'n_NULLABLE_TYPE' ||
        (
          $default_value_node->getTypeName() === 'n_SYMBOL_NAME' &&
          head($default_value_node->getTokens())->getValue() === 'null')
        ) {

        $type_hint .= '|null';
      }
      $types[] = $type_hint;
    }

    return $types;
  }

  /**
   * Check whether types in docblock is equivalent with
   * the PHP type in declaration list.
   *
   * @param  string  Datatype in docblock comment.
   * @param  string  PHP type in declaration list.
   * @return bool
   */
  private function isCompatibleDocblockType($comment_types, $declared_types) {
    $comment_types = explode('|', $comment_types);
    $declared_types = explode('|', $declared_types);

    if (count($comment_types) != count($declared_types)) {
      return false;
    }

    for ($ii = 0; $ii < count($comment_types); $ii++) {
      // `array` is compatible with `list` and `map`.
      if ($declared_types[$ii] === 'array') {
        $type = PhutilTypeSpec::newFromString($comment_types[$ii]);
        if ($type->getType() === 'list' || $type->getType() === 'map') {
          continue;
        }
      }

      $declared_type = array_reverse(explode('\\', $declared_types[$ii]));
      $comment_type = array_reverse(explode('\\', $comment_types[$ii]));

      for ($jj = 0; $jj < count($declared_type); $jj++) {

        if (!isset($comment_type[$jj]) ||
          $declared_type[$jj] != $comment_type[$jj]) {

          return false;
        }
      }
    }

    return true;
  }
}
