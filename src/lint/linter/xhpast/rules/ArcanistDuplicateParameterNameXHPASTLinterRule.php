<?php

final class ArcanistDuplicateParameterNameXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1030;

  public function getLintName() {
    return pht(
      'Duplicate Function Parameter Names');
  }

  public function process(XHPASTNode $root) {
    $parameter_lists =
      $root->selectDescendantsOfType('n_DECLARATION_PARAMETER_LIST');

    foreach ($parameter_lists as $parameter_list) {
      $parameters = $parameter_list->getChildrenOfType(
        'n_DECLARATION_PARAMETER');

      $parameter_name_set = array();
      foreach ($parameters as $parameter) {
        $parameter_node = $parameter->getChildByIndex(1);
        if ($parameter_node->getTypeName() === 'n_VARIABLE_REFERENCE'
          || $parameter_node->getTypeName() === 'n_UNPACK') {

          $parameter_node = $parameter_node->getChildOfType(0, 'n_VARIABLE');
        } else {
          $parameter_node = $parameter->getChildOfType(1, 'n_VARIABLE');
        }

        $parameter_name = $parameter_node->getConcreteString();
        if (isset($parameter_name_set[$parameter_name])) {
          $this->raiseLintAtNode(
            $parameter_node,
            pht('Uses of duplicate parameter names are discouraged.'));
        } else {
          $parameter_name_set[$parameter_name] = true;
        }
      }
    }
  }
}
