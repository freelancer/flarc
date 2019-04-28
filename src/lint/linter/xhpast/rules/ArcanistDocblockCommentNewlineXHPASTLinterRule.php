<?php

final class ArcanistDocblockCommentNewlineXHPASTLinterRule
  extends FlarcXHPASTLinterRule {

  const ID = 2003;

  public function getLintName() {
    return pht('Leading/Trailing NewLines in Docblocks');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $docblocks = $root->selectTokensOfType('T_DOC_COMMENT');

    foreach ($docblocks as $docblock) {
      $stripped_docblock = $this->docblockStrip($docblock->getValue());
      if ($stripped_docblock !== $docblock->getValue()) {
        $this->raiseLintAtToken(
          $docblock,
          pht('Cannot have leading/trailing newline in docblock'),
          $stripped_docblock);
      }
    }
  }

  /**
   * Remove leading/trailing newlines in a docblock.
   *
   * @param  string  Docblock comment.
   * @return string  Stripped docblock comment.
   */
  private function docblockStrip($docblock) {
    $lines = explode(PHP_EOL, $docblock);
    if (count($lines) <= 2) {
      return $docblock;
    }
    for ($begin = 1; $begin < count($lines); $begin++) {
        if (preg_match('/[^\*\s]+/', $lines[$begin])) {
            break;
        }
    }
    for ($end = count($lines) - 2; $end >= 0; $end--) {
        if (preg_match('/[^\*\s]+/', $lines[$end])) {
            break;
      }
    }

    $docblock = $lines[0].PHP_EOL;
    for ($ii = $begin; $ii <= $end; $ii++) {
        $docblock .= $lines[$ii].PHP_EOL;
    }
    $docblock .= end($lines);
    return $docblock;
  }
}
