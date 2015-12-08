<?php

/**
 * @todo Submit this upstream after T27678
 */
final class ArcanistDuplicateMethodDeclarationXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1003;

  public function getLintName() {
    return pht('Duplicate Method Declaration');
  }

  public function process(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfTypes(array(
      'n_CLASS_DECLARATION',
      'n_INTERFACE_DECLARATION',
    ));

    foreach ($classes as $class) {
      $class_name = $class
        ->getChildOfType(1, 'n_CLASS_NAME')
        ->getConcreteString();

      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');
      $declarations = new CaseInsensitiveArray();

      foreach ($methods as $method) {
        $method_name = $method->getChildOfType(2, 'n_STRING');
        $name_string = $method_name->getConcreteString();

        if (isset($declarations[$name_string])) {
          // The values from `CaseInsensitiveArray` are returned by value, and
          // not by reference.
          $method_set   = $declarations[$name_string];
          $method_set[] = $method;
          $declarations[$name_string] = $method_set;

          $this->raiseLintAtNode(
            $method_name,
            pht(
              'Cannot redeclare method `%s`. '.
              'This will cause a PHP fatal error.',
              $class_name.'::'.$name_string));
          continue;
        }

        $declarations[$name_string] = array($method);
      }
    }
  }

}
