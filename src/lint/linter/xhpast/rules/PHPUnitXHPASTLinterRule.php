<?php

abstract class PHPUnitXHPASTLinterRule extends ArcanistXHPASTLinterRule {

  /**
   * Returns all PHPUnit test classes which are descendants of the specified
   * root node.
   *
   * This methods returns all PHPUnit test classes which are descendants of the
   * specified root node. According to the
   * [[https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html |
   * PHPUnit documentation]], a test class is any class which extends from
   * `\PHPUnit_Framework_TestCase`.
   *
   * @param  XHPASTNode        Root node.
   * @param  bool              If `true` then `abstract` classes will be
   *                           excluded from the result.
   * @return list<XHPASTNode>  All PHPUnit test classes.
   */
  final protected function getTestClasses(
    XHPASTNode $root,
    $exclude_abstract_classes = false) {

    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    $test_classes = array();

    foreach ($classes as $class) {
      $extends = $class->getChildByIndex(2);

      if ($exclude_abstract_classes) {
        // TODO: `XHPASTNode` should maybe provide an `isAbstractClass` method.
        $class_attributes = $class
          ->getChildOfType(0, 'n_CLASS_ATTRIBUTES')
          ->getChildrenOfType('n_STRING');

        foreach ($class_attributes as $class_attribute) {
          // Class attributes are case-insensitive.
          $class_attribute = strtolower($class_attribute->getConcreteString());

          if ($class_attribute == 'abstract') {
            continue 2;
          }
        }
      }

      if ($extends->getTypeName() == 'n_EMPTY') {
        // The class doesn't extend any other class and, as such,
        // cannot be a PHPUnit test class.
        continue;
      }

      $extends_class = $extends
        ->getChildOfType(0, 'n_CLASS_NAME')
        ->getConcreteString();

      // Class names in PHP are case-insensitive.
      $extends_class = strtolower($extends_class);

      if ($extends_class[0] == '\\') {
        if ($extends_class == '\\phpunit_framework_testcase') {
          $test_classes[] = $class;
        }

        // There is no need to check the `use` mapping because the class being
        // extends is prefixed with `\`, which means that the symbol name
        // refers to the global namespace.
        continue;
      }

      if ($class->getNamespace() === null &&
          $extends_class == 'phpunit_framework_testcase') {
        $test_classes[] = $class;
        continue;
      }

      $use_mapping = $this->getUseMapping($class);

      // Note that the default value is `$extends_class` because if the symbol
      // doesn't exist in the `use` mapping then it used unaliased.
      //
      // TODO: We can't use `idx` here until after
      // https://secure.phabricator.com/D15133 has landed.
      if (isset($use_mapping[$extends_class])) {
        $extends_class = $use_mapping[$extends_class];
      }

      // Class names in PHP are case-insensitive.c
      $extends_class = strtolower($extends_class);

      if ($extends_class == '\\phpunit_framework_testcase') {
        $test_classes[] = $class;
        continue;
      }

      // Allow classes annotated with `@testClass` to be identified as PHPUnit
      // test classes.
      if ($docblock = $class->getDocblockToken()) {
        $parser = new PhutilDocblockParser();
        list($text, $specials) = $parser->parse($docblock->getValue());

        if (idx($specials, 'testClass')) {
          $test_classes[] = $class;
          continue;
        }
      }
    }

    return $test_classes;
  }

  /**
   * Returns all PHPUnit test methods which are descendants of the specified
   * root node.
   *
   * This methods returns all PHPUnit test methods which are descendants of the
   * specified root node. According to the
   * [[https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html |
   * PHPUnit documentation]], test methods are either named as `test*` or have
   * a `@test` annotation in the docblock.
   *
   * @param  XHPASTNode        Root node.
   * @return list<XHPASTNode>  All PHPUnit test classes.
   */
  final protected function getTestMethods(XHPASTNode $root) {
    $test_classes = $this->getTestClasses($root);
    $test_methods = array();

    foreach ($test_classes as $test_class) {
      $methods = $test_class->selectDescendantsOfType('n_METHOD_DECLARATION');

      foreach ($methods as $method) {
        $method_name = $method
          ->getChildOfType(2, 'n_STRING')
          ->getConcreteString();

        if (substr($method_name, 0, 4) == 'test') {
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
    }

    return $test_methods;
  }

  /**
   * Return the `use` mapping which applies to the specified node.
   *
   * A "`use` mapping" is a mapping of aliased symbol name to imported symbol
   * name. This mapping can be used to resolve ambiguous symbol references.
   *
   * For example, consider the following code:
   *
   * ```lang=php
   * use X;
   * use Y as Z;
   * ```
   *
   * The corresponding `use` mapping is:
   *
   * ```lang=php
   * array(
   *   'X' => '\\X',
   *   'Z' => '\\Y',
   * )
   * ```
   *
   * @param  XHPASTNode           The input node. The `use` mapping will be
   *                              calculated for this node.
   * @return map<string, string>  The corresponding `use` mapping, stored in a
   *                              `CaseInsensitiveArray`.
   *
   * @todo Submit this upstream.
   */
  public static function getUseMapping(XHPASTNode $node) {
    $mapping   = new CaseInsensitiveArray();
    $statement = $node->getParentNode();

    // TODO: There's no reason that we can't generate a `use` mapping for other
    // node types. For example, it's perfectly reasonable to generate a `use`
    // mapping for a node of type `n_FUNCTION_DECLARATION`.
    if ($node->getTypeName() != 'n_CLASS_DECLARATION') {
      throw new InvalidArgumentException(
        pht(
          "Expected node of type '%'!",
          'n_CLASS_DECLARATION'));
    }

    while ($statement) {
      $use_lists = $statement->selectDescendantsOfType('n_USE_LIST');

      foreach ($use_lists as $use_list) {
        $uses = $use_list->getChildrenOfType('n_USE');

        foreach ($uses as $use) {
          $symbol = $use
            ->getChildOfType(0, 'n_SYMBOL_NAME')
            ->getConcreteString();

          $alias = $use->getChildByIndex(1);

          if ($alias->getTypeName() == 'n_EMPTY') {
            $alias = ltrim($symbol, '\\');
          } else {
            $alias = $alias->getConcreteString();
          }

          $mapping[$alias] = '\\'.ltrim($symbol, '\\');
        }
      }

      $statement = $statement->getPreviousSibling();
    }

    return $mapping;
  }

}
