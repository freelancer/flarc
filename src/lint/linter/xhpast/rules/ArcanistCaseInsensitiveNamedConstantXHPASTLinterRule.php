<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistCaseInsensitiveNamedConstantXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1023;

  public function getLintName() {
    return pht('Case-Insensitive Named Constant');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $defines = $this->getFunctionCalls($root, array('define'));

    foreach ($defines as $define) {
      $parameters = $define->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      // If the function call has less than three parameters then the constant
      // is being defined in a case-sensitive manner.
      if (count($parameters->getChildren()) < 3) {
        continue;
      }

      // If the third parameter of the function call is `false` then the
      // constant is being defined in a case-sensitive manner.
      if ($parameters->getChildByIndex(2)->evalStatic() === false) {
        continue;
      }

      $this->raiseLintAtNode(
        $define,
        pht(
          'Named constants defined with `%s` should not be made '.
          'case-insensitive as this hinders static analysis.',
          'define'));
    }
  }

}
