<?php

final class PHPUnitCoversXHPASTLinterRule extends ArcanistXHPASTLinterRule {

  const ID = 1001;

  public function getLintName() {
    return pht('Explicit Coverage Specification');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $parser = new PhutilDocblockParser();

    foreach ($this->getTestClasses($root) as $class) {
      foreach ($this->getTestMethods($class) as $method) {
        $docblock = $method->getDocblockToken();

        if (!$docblock) {
          $this->raiseLintAtNode(
            $method,
            pht(
              'This test method does not use a `%s` annotation '.
              'or a `%s annotation.',
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
            'This docblock does not contain a  `%s` annotation '.
            'or a `%s` annotation.',
            '@covers',
            '@coversNothing'));
      }
    }
  }

  /**
   * Returns all PHPUnit test classes which are descendants of the specified
   * root node.
   *
   * This methods returns all PHPUnit test classes which are descendants of the
   * specified root node. According to the
   * [[https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html |
   * PHPUnit documentation]], test classes inherit from
   * `PHPUnit_Framework_TestCase` (or `\PHPUnit_Framework_TestCase` if the
   * class is in a namespace).
   *
   * @param  XHPASTNode        Root node.
   * @return list<XHPASTNode>
   *
   * @todo This method doesn't handle namespaces correctly. It assumes
   *       namespaces are always at the top of the root.
   */
  private function getTestClasses(XHPASTNode $root) {
    $test_classes = array();
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    $namespaces = $root->selectDescendantsOfType('n_NAMESPACE');
    $force_global_namespace = false;

    if ($namespaces) {
      $force_global_namespace = true;
    }

    foreach ($classes as $class) {
      $extends = $class->getChildByIndex(2);

      if ($extends->getTypeName() != 'n_EXTENDS_LIST') {
        // The class doesn't extend anything
        continue;
      }

      $extends_class_name =
        strtolower($extends->getChildOfType(0, 'n_CLASS_NAME')
          ->getConcreteString());
      $is_test_class = ($extends_class_name == '\phpunit_framework_testcase');

      if (!$force_global_namespace) {
        $is_test_class = $is_test_class ||
          $extends_class_name == 'phpunit_framework_testcase';
      }

      if ($is_test_class) {
        $test_classes[] = $class;
      }
    }

    return $test_classes;
  }

  /**
   * Returns all PHPUnit test methods which are descendants of the specified
   * root node (which is assumed to be a PHPUnit test class).
   *
   * This methods returns all PHPUnit test methods which are descendants of the
   * specified root node. This root node is assumed to be a PHPUnit test class.
   * According to the
   * [[https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html |
   * PHPUnit documentation]], test methods either have a `@test` annotation or
   * start with the word `test`.
   *
   * @param  XHPASTNode        Root node.
   * @return list<XHPASTNode>
   */
  private function getTestMethods(XHPASTNode $root) {
    $test_methods = array();
    $methods = $root->selectDescendantsOfType('n_METHOD_DECLARATION');

    foreach ($methods as $method) {
      $name = $method
        ->getChildOfType(2, 'n_STRING')
        ->getConcreteString();

      if (substr($name, 0, 4) == 'test') {
        $test_methods[] = $method;
        continue;
      }

      if ($docblock = $method->getDocblockToken()) {
        $parser = new PhutilDocblockParser();
        list($text, $specials) = $parser->parse($docblock->getValue());

        if (idx($specials, 'test')) {
          $test_methods[] = $method;
          continue;
        }
      }
    }

    return $test_methods;
  }
}
