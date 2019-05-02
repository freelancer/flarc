<?php

final class ArcanistGeneratorUseReturnXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1027;

  private $closures;

  public function getLintName() {
    return pht('Generators cannot return values using `%s`.', 'return');
  }

  public function process(XHPASTNode $root) {
    // Generators can have return values in PHP 7.0. See
    // http://php.net/manual/en/migration70.new-features.php#migration70.new-features.generator-return-expressions.
    if ($this->version && version_compare($this->version, '7.0.0', '>=')) {
      return;
    }

    $functions = $root->selectDescendantsOfTypes([
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ]);

    foreach ($functions as $function) {

      $this->closures = $this->getAnonymousClosures($function);

      $returns = array_filter(
        iterator_to_array($function->selectDescendantsOfType('n_RETURN')),
        [$this, 'closureStatementFilter']);

      $yields = array_filter(
        iterator_to_array($function->selectDescendantsOfType('n_YIELD')),
        [$this, 'closureStatementFilter']);

      if ($returns && $yields) {
        foreach ($returns as $return) {
          $this->raiseLintAtNode(
            $return,
            pht(
              'Generators cannot return values using "`%s`"',
              'return'));
        }
      }
    }
  }

  /**
   * This filter filters out statements inside a closure definition.
   *
   * @param  XHPASTNode  Node
   * @return bool
   */
  private function closureStatementFilter(XHPASTNode $node) {
    foreach ($this->closures as $closure) {
      if ($node->isDescendantOf($closure)) {
        return false;
      }
    }
    return true;
  }
}
