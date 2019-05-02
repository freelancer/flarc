<?php

final class ArcanistGlobalConstantXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1004;

  public function getLintName() {
    return pht('Global Constant Definition');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $constants = $this
      ->getFunctionCalls($root, ['define'])
      ->add($root->selectDescendantsOfType('n_CONSTANT_DECLARATION_LIST'));

    foreach ($constants as $constant) {
      $this->raiseLintAtNode(
        $constant,
        pht(
          'Limit the use of defined constants. See %s for more information.',
          'T13418'));
    }
  }

}
