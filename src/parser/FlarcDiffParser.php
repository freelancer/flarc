<?php

/**
 * Class FlarcDiffParser Generic Parser for all kinds of diff output
 */
final class FlarcDiffParser extends Phobject {

  /**
   * Parse a diff into format of array of @{class:FlarcDiffHunk} objects.
   *
   * @param  string               the full diff generated
   * @return list<FlarcDiffHunk>  array of replacements
   */
  public function parseDiff(string $raw_diff): array {
    $parsed_hunks = [];
    foreach ($this->getHunks($raw_diff) as $raw_hunk) {
      $parsed_hunks[] = $this->parseHunk($raw_hunk);
    }
    return $parsed_hunks;
  }

  /**
   * parse the raw hunk into FlarcDiffHunk object
   *
   * @param  array          raw hunk that has line and text
   * @return FlarcDiffHunk  parsed hunk
   */
  private function parseHunk(array $raw_hunk) {
    $diff = idx($raw_hunk, 'text');
    $line_num = idx($raw_hunk, 'line');
    $original = $replacement = [];

    $lines = explode("\n", $diff);
    foreach ($lines as $line) {
      $sig = $line === '' ? null : $line[0];
      $line = substr($line, 1);

      switch ($sig) {
        case '+':
          $replacement[] = $line;
          break;
        case '-':
          $original[] = $line;
          break;
        case '\\':
          break;
        default:
          $original[] = $line;
          $replacement[] = $line;
          break;
      }
    }

    return new FlarcDiffHunk(
      $line_num,
      implode("\n", $original),
      implode("\n", $replacement));
  }

  /**
   * Split the full diff into smaller hunks separated by regex /@@.*?@@/m
   *
   * @param  string    raw generated diff
   * @return iterable  each yield gives a ['line' => $line, 'text' => $text]
   */
  private function getHunks($raw_diff) {
    $regex_diff = '/^@@\s+-(?P<origLine>\d+),.*?@@\n?/ms';
    $diffs = preg_split($regex_diff, $raw_diff, 0, PREG_SPLIT_DELIM_CAPTURE);
    array_shift($diffs); // ignore the header of diff

    // bind them into pairs
    for ($i = 0; $i < count($diffs); $i += 2) {
      yield ['line' => $diffs[$i], 'text' => $diffs[$i + 1]];
    }

  }
}
