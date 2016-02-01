<?php

final class PHPUnitCoversDefaultClassXHPASTLinterRule
  extends PHPUnitXHPASTLinterRule {

  const ID = 1021;

  public function getLintName() {
    return pht(
      'Explicit `%s` Specification',
      '@coversDefaultClass');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $parser = new PhutilDocblockParser();

    foreach ($this->getTestClasses($root, true) as $class) {
      $docblock = $class->getDocblockToken();
      $message = pht(
        'This PHPUnit test class does not specify a `%s` annotation. '.
        'You should use `%s` to simplify `%s` annotations. Alternatively, '.
        'you can annotate the PHPUnit test class with `%s` to disable '.
        'this linter rule.',
        '@coversDefaultClass',
        '@coversDefaultClass',
        '@covers',
        '@coversNoDefaultClass');

      if (!$docblock) {
        $this->raiseLintAtNode($class, $message);
        continue;
      }

      list($text, $specials) = $parser->parse($docblock->getValue());

      if (idx($specials, 'coversDefaultClass') ||
          idx($specials, 'coversNoDefaultClass')) {
        continue;
      }

      $this->raiseLintAtToken($docblock, $message);
    }
  }

}
