<?php

final class ArcanistSapiNameXHPASTLinterRule extends ArcanistXHPASTLinterRule {

  const ID = 1022;

  public function getLintName() {
    return pht('Use of `%s` Function', 'php_sapi_name');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $function_calls = $this->getFunctionCalls($root, ['php_sapi_name']);

    foreach ($function_calls as $function_call) {
      $parameters = $function_call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      // If the function call has any arguments, don't complain... just in case.
      if ($parameters->getChildren()) {
        continue;
      }

      $this->raiseLintAtNode(
        $function_call,
        pht(
          '`%s` should be used instead of `%s`. The PHP constant `%s` has the '.
          'same value as `%s`, but function calls have additional overhead.',
          'PHP_SAPI',
          'php_sapi_name()',
          'PHP_SAPI',
          'php_sapi_name()'),
        'PHP_SAPI');
    }
  }
}
