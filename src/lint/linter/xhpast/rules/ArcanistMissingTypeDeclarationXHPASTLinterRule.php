<?php

final class ArcanistMissingTypeDeclarationXHPASTLinterRule
  extends FlarcXHPASTLinterRule {

  const ID = 2005;

  public function getLintName(): string {
    return pht('Missing Type Declaration');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    if (version_compare($this->version, '7.0.0', '<')) {
      return;
    }

    $nodes = $root->selectDescendantsOfTypes(array(
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ));

    foreach ($nodes as $node) {
      $param_tags = $this->getDocblockTag($node, 'param');
      if (is_string($param_tags)) {
        $param_tags = [$param_tags];
      } else if ($param_tags === null) {
        $param_tags = [];
      }
      $parameter_nodes = $node
        ->getChildOfType(3, 'n_DECLARATION_PARAMETER_LIST')
        ->getChildrenOfType('n_DECLARATION_PARAMETER');

      for ($ii = 0; $ii < count($parameter_nodes); $ii++) {
        $parameter = $parameter_nodes[$ii];

        $type_hint = $parameter->getChildByIndex(0);
        $variable  = $parameter->getChildByIndex(1);

        if ($variable->getTypeName() == 'n_VARIABLE_REFERENCE') {
          $variable = $variable->getChildOfType(0, 'n_VARIABLE');
        }

        if ($type_hint->getTypeName() !== 'n_EMPTY') {
          continue;
        }
        $type_annotation = head(explode(' ', idx($param_tags, $ii, '')));
        if ($this->isNullableDocblockType($type_annotation)) {
          $this->raiseLintAtNode(
            $variable,
            pht(
              'Type declaration is missing for nullable parameter `%s`.',
              $variable->getConcreteString()));
        }
        if (!$this->isUnionDocblockType($type_annotation)) {
          $this->raiseLintAtNode(
            $variable,
            pht(
              'Type declaration is missing for parameter `%s`. If you cannot add type '.
              'declaration because the parameter could be multiple types, you should '.
              'document it using `%s type1|type2` or `%s %s` in the docblock.',
              $variable->getConcreteString(),
              '@param',
              '@param', 'mixed'));
        }
      }
    }
  }
}
