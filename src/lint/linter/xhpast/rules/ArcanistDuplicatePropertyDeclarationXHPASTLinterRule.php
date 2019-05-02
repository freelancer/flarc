<?php

final class ArcanistDuplicatePropertyDeclarationXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1006;

  public function getLintName() {
    return pht('Duplicate Property Declaration');
  }

  public function process(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($classes as $class) {
      $class_name = $class
        ->getChildOfType(1, 'n_CLASS_NAME')
        ->getConcreteString();

      $properties = $class->selectDescendantsOfType(
        'n_CLASS_MEMBER_DECLARATION');
      $declarations = [];

      foreach ($properties as $property) {
        $property_name_node = $property->getChildOfType(0, 'n_VARIABLE');
        $property_name = $property_name_node->getConcreteString();

        if (isset($declarations[$property_name])) {
          $declarations[$property_name][] = $property;

          $this->raiseLintAtNode(
            $property_name_node,
            pht(
              'Cannot redeclare property `%s`. '.
              'This will cause a PHP fatal error.',
              $class_name.'::'.$property_name));
          continue;
        }

        $declarations[$property_name] = [$property];
      }
    }
  }

}
