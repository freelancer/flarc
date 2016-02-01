<?php

final class PHPUnitCoversXHPASTLinterRule extends PHPUnitXHPASTLinterRule {

  const ID = 1001;

  public function getLintName() {
    return pht(
      'Explicit `%s` Specification',
      '@covers');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $parser = new PhutilDocblockParser();

    foreach ($this->getTestMethods($root) as $method) {
      $docblock = $method->getDocblockToken();
      $message = pht(
        'This test method does not specify a `%s` or `%s` annotation.'.
        'You should use the `%s` annotation to indicate covered code.',
        '@covers',
        '@coversNothing',
        '@covers');

      if (!$docblock) {
        $this->raiseLintAtNode($method, $message);
        continue;
      }

      list($text, $specials) = $parser->parse($docblock->getValue());

      if (idx($specials, 'covers') || idx($specials, 'coversNothing')) {
        continue;
      }

      $this->raiseLintAtToken($docblock, $message);
    }
  }

}
