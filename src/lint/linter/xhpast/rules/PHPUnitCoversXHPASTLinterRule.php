<?php

final class PHPUnitCoversXHPASTLinterRule extends PHPUnitXHPASTLinterRule {

  const ID = 1001;

  public function getLintName() {
    return pht('Explicit Coverage Specification');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $parser = new PhutilDocblockParser();

    foreach ($this->getTestMethods($root) as $method) {
      $docblock = $method->getDocblockToken();

      if (!$docblock) {
        $this->raiseLintAtNode(
          $method,
          pht(
            'This test method does not specify a `%s` or `%s` annotation.',
            '@covers',
            '@coversNothing'));
        continue;
      }

      list($text, $specials) = $parser->parse($docblock->getValue());

      if (idx($specials, 'covers') || idx($specials, 'coversNothing')) {
        continue;
      }

      $this->raiseLintAtToken(
        $docblock,
        pht(
          'This docblock does not contain a `%s` or `%s` annotation.',
          '@covers',
          '@coversNothing'));
    }
  }

}
