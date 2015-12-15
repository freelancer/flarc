<?php

/**
 * @todo Remove this after https://secure.phabricator.com/D13917.
 * @todo Submit this upstream after T27678.
 */
final class ArcanistGlobalConstantXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1004;

  public function getLintName() {
    return pht('Global Constant Definition');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $function_calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');

    foreach ($function_calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();

      if (strtolower($name) == 'define') {
        $this->raiseLintAtNode(
          $call,
          pht(
            'Limit the use of defined constants. See %s for more information.',
            'T13418'));
      }
    }
  }

}
