<?php

final class ArcanistArrayPushXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1012;

  public function getLintName() {
    return pht('Unnecessary Call to `%s`', 'array_push');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $array_pushes = $this->getFunctionCalls($root, array('array_push'));

    foreach ($array_pushes as $array_push) {
      $parameters = $array_push->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      // Skip if there are an unexpected number of parameters.
      //
      // In particular, `array_push($x, 1, 2, 3)` is more performant than
      // `$x[] = 1; $x[] = 2; $x[] = 3;`.
      if (count($parameters->getChildren()) != 2) {
        continue;
      }

      // If the first parameter is not a variable then the call to `array_push`
      // is probably wrong (e.g. `array_push(null, 'derp')`). In any case,
      // don't raise any linter messages here.
      $array = $parameters->getChildByIndex(0);
      if ($array->getTypeName() != 'n_VARIABLE' &&
          $array->getTypeName() != 'n_OBJECT_PROPERTY_ACCESS') {
        continue;
      }

      // If the return value from `array_push` is being used, don't raise a
      // linter message.
      if ($array_push->getParentNode()->getTypeName() != 'n_STATEMENT') {
        continue;
      }

      $this->raiseLintAtNode(
        $array_push,
        pht(
          '`%s` should be used instead of `%s` when the '.
          'return value is unused.',
          '$array[] = $element',
          'array_push($array, $element)'),
        sprintf(
          '%s[] = %s',
          $array->getConcreteString(),
          $parameters->getChildByIndex(1)->getConcreteString()));

    }
  }
}
