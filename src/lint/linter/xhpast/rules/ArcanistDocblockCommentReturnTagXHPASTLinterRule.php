<?php

final class ArcanistDocblockCommentReturnTagXHPASTLinterRule
  extends FlarcXHPASTLinterRule {

  const ID = 2002;

  private $interfaces;

  public function getLintName() {
    return pht('Incorrect Docblock `%s` Tag', '@return');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $functions = $root->selectDescendantsOfTypes(array(
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ));
    $this->interfaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');

    foreach ($functions as $function) {
      if (!$this->isLintable($function)) {
        continue;
      }

      $linters = array(
        array($this, 'lintMultipleReturnTags'),
        array($this, 'lintReturnVoid'),
        array($this, 'lintNoReturnTag'),
        array($this, 'lintReturnGenerator'),
        array($this, 'lintNoReturnStatement'),
      );

      foreach ($linters as $linter) {
        if ($linter($function)) {
          continue 2;
        }
      }
    }
  }

  /**
   * Lint function/method docblock containing multiple `@return` tags.
   *
   * @param  XHPASTNode  Node
   * @return bool
   */
  public function lintMultipleReturnTags(XHPASTNode $node) {
    $return_tag = $this->getDocblockTag($node, 'return');

    if (is_array($return_tag)) {
      $this->raiseLintAtToken(
        $node->getDocblockToken(),
        pht(
          'Docblock comments should contain only a single `%s` tag.',
          '@return'));
      return true;
    }
    return false;
  }

  /**
   * Lint function/method docblock containing `@return` tag.
   *
   * @param  XHPASTNode  Node
   * @return bool
   */
  public function lintNoReturnTag(XHPASTNode $node) {
    $return_tag = $this->getDocblockTag($node, 'return');
    list($returns, $yields) = $this->getReturnAndYieldNodes($node);
    if ($return_tag === null) {
      if (!empty($returns) || !empty($yields)) {
        $docblock = $node->getDocblockToken();
        $this->raiseLintAtToken(
          $docblock,
          pht(
            'This docblock comment does not have a `%s` tag. Functions '.
            'and methods should explicitly annotate the type of their '.
            'return values.',
            '@return'));
      }
      return true;
    }
    return false;
  }

  /**
   * Lint function/method docblock containing `@return void` tag.
   *
   * @param  XHPASTNode  Node
   * @return bool
   */
  public function lintReturnVoid(XHPASTNode $node) {
    list($returns, $yields) = $this->getReturnAndYieldNodes($node);
    $return_tag = $this->getDocblockTag($node, 'return');
    $found_error = false;
    if ($return_tag  === 'void') {
      foreach ($returns as $return) {
        $return_value = $return->getChildByIndex(0);

        if ($return_value->getTypeName() != 'n_EMPTY') {
          $this->raiseLintAtNode(
            $return,
            pht(
              'This `%s` statement conflicts with the `%s` docblock tag.',
              'return',
              '@return void'));
        }
        $found_error = true;
      }
    }
    return $found_error;
  }

  /**
   * Lint function/method docblock without `return` or `yield` statement.
   *
   * @param  XHPASTNode  Node
   * @return bool
   */
  public function lintNoReturnStatement(XHPASTNode $node) {
    list($returns, $yields) = $this->getReturnAndYieldNodes($node);
    if (empty($returns) && empty($yields)) {
      $return_tag = $this->getDocblockTag($node, 'return');
      if ($return_tag !== null && $return_tag !== 'void') {
        $this->raiseLintAtNode(
          $node,
          pht(
            'This function/method does not have any `%s` or `%s` statement.',
            'return',
            'yield'));
      }
      return true;
    }
    return false;
  }

  /**
   * Lint generator docblock.
   *
   * @param  XHPASTNode  Node
   * @return bool
   */
  public function lintReturnGenerator(XHPASTNode $node) {
    list($_, $yields) = $this->getReturnAndYieldNodes($node);
    $return_tag = $this->getDocblockTag($node, 'return');
    $docblock = $node->getDocblockToken();
    if (!empty($yields) && $return_tag !== 'Generator') {
      $this->raiseLintAtToken(
        $docblock,
        pht(
          'This docblock comment does not have a `%s` tag. Generators '.
          'should explicitly annotate the type of their return values '.
          'to `%s`.',
          '@return Generator',
          'Generator'));
      return true;
    }

    if (empty($yields) && $return_tag === 'Generator') {
      $this->raiseLintAtToken(
        $docblock,
        pht(
          'This function/method is not a generator, but its docblock has a `%s` tag',
          '@return Generator'));
      return true;
    }
    return false;
  }

  /**
   * Check whether the given node can be linted.
   *
   * @param  XHPASTNode A function node.
   * @return bool
   */
  public function isLintable(XHPASTNode $function) {
    $docblock = $function->getDocblockToken();

    if (!$docblock) {
      return false;
    }

    if ($function->getTypeName() == 'n_METHOD_DECLARATION') {
      // Methods in interfaces do not have implementation so we just ignore
      // them.
      foreach ($this->interfaces as $interface) {
        if ($function->isDescendantOf($interface)) {
          return false;
        }
      }
      // Abstract methods do not have implementations so we ignore them
      // too.
      $modifier_list = $function->getChildByIndex(0);
      foreach ($modifier_list->getChildren() as $node) {
        if ($node->getConcreteString() == 'abstract') {
          return false;
        }
      }
    }

    // For methods that define the `@inheritDoc` annotation, for now we
    // simply ignore them because we cannot work out docblock inheritance.
    list($_, $_, $tags) = $this->parseDocblock($docblock);
    if ($function->getTypeName() == 'n_METHOD_DECLARATION'
        && isset($tags['inheritDoc'])) {
      return false;
    }

    return true;
  }

  /**
   * Get `return` and `yield` nodes from a function node.
   *
   * NOTE: `return`s and `yield`s in closures are ignored.
   *
   * @param  XHPASTNode A function node.
   * @return pair<list<XHPASTNode>,list<XHPASTNode>> Lists of `return` and `yield` nodes.
   */
  public function getReturnAndYieldNodes(XHPASTNode $function) {
    $returns = array();
    $yields = array();

    $closures = $this->getAnonymousClosures($function);
    foreach ($function->selectDescendantsOfTypes(array('n_RETURN', 'n_YIELD')) as $node) {
      foreach ($closures as $closure) {
        if ($node->isDescendantOf($closure)) {
          continue 2;
        }
      }

      if ($node->getTypeName() === 'n_RETURN') {
        $returns[] = $node;
      } else if ($node->getTypeName() === 'n_YIELD') {
        $yields[] = $node;
      }
    }
    return array($returns, $yields);
  }
}
