<?php

final class ArcanistMissingTypeAnnotationXHPASTLinterRule
  extends FlarcXHPASTLinterRule {

  const ID = 2006;

  public function getLintName() {
    return pht('Missing type annotation');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfTypes([
      'n_CLASS_MEMBER_MODIFIER_LIST',
    ]);

    foreach ($nodes as $node) {
      if (!$this->getDocBlockTag($node, 'var')) {
        $this->raiseLintAtNode(
          $node,
          pht('Missing `%s` annotation on the class property.', '@var'));
      }
    }
  }
}
