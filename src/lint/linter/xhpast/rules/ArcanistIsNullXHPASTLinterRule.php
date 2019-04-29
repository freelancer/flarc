<?php

final class ArcanistIsNullXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1018;

  public function getLintName() {
    return pht('Unnecessary Call to `%s`', 'is_null');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $calls = $this->getFunctionCalls($root, ['is_null']);

    foreach ($calls as $call) {
      $parameters = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      // Skip if there are an unexpected number of parameters.
      if (count($parameters->getChildren()) != 1) {
        continue;
      }

      $parameter = $parameters->getChildByIndex(0);
      $parent    = $call->getParentNode();

      $node    = $call;
      $replace = sprintf('%s === null', $parameter->getConcreteString());

      if ($parent->getTypeName() == 'n_UNARY_PREFIX_EXPRESSION') {
        $grandparent = $parent->getParentNode();
        $operator    = $parent->getChildOfType(0, 'n_OPERATOR');

        // Improve handling of negation. It is preferable to transform
        // `!is_null($x)` into `$x !== null` rather than `!($x === null)`.
        if ($operator->getConcreteString() == '!') {
          $node    = $parent;
          $replace = sprintf('%s !== null', $parameter->getConcreteString());
        }

        if ($grandparent->getTypeName() == 'n_UNARY_PREFIX_EXPRESSION') {
          $replace = '('.$replace.')';
        }
      }

      $this->raiseLintAtNode(
        $node,
        pht(
          'For consistency, `%s` should be used instead of `%s`.',
          '$x === null',
          'is_null($x)'),
        $replace);
    }
  }
}
