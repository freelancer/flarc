<?php

final class FlarcDiffHunk extends Phobject {

  private $line;
  private $original;
  private $replacement;

  public function __construct($line, $orig, $new) {
    $this->line = $line;
    $this->original = $orig;
    $this->replacement = $new;
  }

  /**
   * @return int the line number of this change
   */
  public function getLine(): int {
    return $this->line;
  }

  /**
   * @return string the original text
   */
  public function getOriginal(): string {
    return $this->original;
  }

  /**
   * @return string the replacement text
   */
  public function getReplacement(): string {
    return $this->replacement;
  }

}
