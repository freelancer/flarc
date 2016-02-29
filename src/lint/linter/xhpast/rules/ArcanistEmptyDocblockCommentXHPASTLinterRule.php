<?php

final class ArcanistEmptyDocblockCommentXHPASTLinterRule
  extends ArcanistDocblockCommentXHPASTLinterRule {

  const ID = 2000;

  public function getLintName() {
    return pht('Empty Docblock Comment');
  }

  public function process(XHPASTNode $root) {
    $docblock_comments = $root->selectTokensOfType('T_DOC_COMMENT');

    foreach ($docblock_comments as $docblock_comment) {
      list($summary, $description, $tags) = $this->parse($docblock_comment);

      if (strlen($summary) || strlen($description) || $tags) {
        continue;
      }

      $this->raiseLintAtToken(
        $docblock_comment,
        pht("Empty docblock comments don't typically provide any real value."),
        '');
    }
  }

}
