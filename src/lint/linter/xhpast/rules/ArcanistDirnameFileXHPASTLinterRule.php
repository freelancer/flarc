<?php

/**
 * @todo Remove this after https://secure.phabricator.com/D13426.
 * @todo Submit this upstream after T27678.
 */
final class ArcanistDirnameFileXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1009;

  public function getLintName() {
    return pht('%s Usage', 'dirname(__FILE__)');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $function_calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');

    foreach ($function_calls as $function_call) {
      $name = $function_call->getChildByIndex(0)->getConcreteString();
      $name = strtolower($name);
      $args = $function_call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      if ($name == 'dirname' && count($args->getChildren()) == 1) {
        $arg = $args->getChildByIndex(0);

        if ($arg->getTypeName() == 'n_MAGIC_SCALAR' &&
            strtoupper($arg->getSemanticString()) == '__FILE__') {

          $this->raiseLintAtNode(
            $function_call,
            pht(
              'Use `%s` instead of `%s`.',
              '__DIR__',
              'dirname(__FILE__)'),
            '__DIR__');
        }
      }
    }
  }

}
