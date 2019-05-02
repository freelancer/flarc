<?php

final class ArcanistClosureInstantiationXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1028;

  public function getLintName() {
    return pht('Instantiation of `%s`', 'Closure');
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfType('n_NEW');

    foreach ($nodes as $node) {
      $class = $node->getChildByIndex(0);

      if ($class->getTypeName() != 'n_CLASS_NAME') {
        continue;
      }

      // Class names in PHP are case-insensitive.
      $class_name = strtolower($class->getConcreteString());

      if (
        $class->getNamespace() === null && $class_name == 'closure' ||
        $class_name === '\\closure') {

        $this->raiseLintAtNode(
          $node,
          pht(
            'Instantiation of `%s` is not allowed and will cause a '.
            'fatal error.',
            'Closure'));
      }
    }
  }

}
