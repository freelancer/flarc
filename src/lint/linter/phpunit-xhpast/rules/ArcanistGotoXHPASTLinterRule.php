<?php

final class ArcanistGotoXHPASTLinterRule extends ArcanistXHPASTLinterRule {

  const ID = 1002;

  public function getLintName() {
    return pht('Use of `%s` Statement', 'goto');
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfTypes(array(
      'n_GOTO',
      'n_LABEL',
    ));

    foreach ($nodes as $node) {
      switch ($node->getTypeName()) {
        case 'n_GOTO':
          $message = pht(
            '`%s` statements should not be used.'.
            ' [[http://imgs.xkcd.com/comics/goto.png | XKCD]]'.
            ' explains why.', 'goto');
          break;

        case 'n_LABEL':
          $message = pht(
            'Label associated with `%s` statements should not be used.'.
            ' [[http://imgs.xkcd.com/comics/goto.png | XKCD]]'.
            ' explains why.', 'goto');
          break;
      }

      $this->raiseLintAtNode(
        $node,
        $message);
    }
  }

}
