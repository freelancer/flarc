<?php

final class ArcanistReturnNullInsteadOfFalseXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1032;

  public function getLintName(): string {
    return pht('Return `%s` instead of `%s`', 'null', 'false');
  }

  public function getLintSeverity(): string {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root): void {
    $declarations = $root->selectDescendantsOfTypes(
      ['n_FUNCTION_DECLARATION', 'n_METHOD_DECLARATION']);

    foreach ($declarations as $declaration) {
      $return_type = $declaration->getChildByIndex(5);
      if (strtolower($return_type->getConcreteString()) == 'bool') {
        continue;
      }

      $closures = $this->getAnonymousClosures($declaration);
      foreach ($declaration->selectDescendantsOfType('n_RETURN') as $return) {
        foreach ($closures as $closure) {
          if ($return->isDescendantOf($closure)) {
            continue 2;
          }
        }
        $return_value = $return->getChildByIndex(0);
        if (strtolower($return_value->getConcreteString()) === 'false') {
          $this->raiseLintAtNode(
            $return_value,
            pht(
              'You should return `%s` rather than `%s` to indicate the '.
              'absence of a return value and declare the return type as '.
              'nullable type. If this function is intended to return a boolean '.
              'value, you can disregard this message (but you should explicitly '.
              'declare the return type for this function as `%s`)',
              'null', 'false', 'bool'));
        }
      }
    }
  }

}
