<?php

final class PHPUnitXHPASTLinterRuleTestCase extends PhutilTestCase {

  public function testUseMapping() {
    $dir   = __DIR__.'/use-mapping/';
    $files = id(new FileFinder($dir))
      ->withType('f')
      ->withSuffix('php.test')
      ->find();

    foreach ($files as $file) {
      list($tree, $expect) = $this->readTestData($dir.'/'.$file);

      $root    = $tree->getRootNode();
      $classes = $root->selectDescendantsOfTypes([
        'n_CLASS_DECLARATION',
        'n_INTERFACE_DECLARATION',
        'n_FUNCTION_DECLARATION',
      ]);

      foreach ($classes as $class) {
        $id = (string)$class->getID();

        if (idx($expect, $id) === null) {
          throw new Exception(
            pht(
              'No expected value for node %d in file "%s".',
              $class->getID(),
              $file));
        }

        $expected = $expect[$id];
        asort($expected);

        $actual = PHPUnitCoversXHPASTLinterRule::getUseMapping($class)
          ->toArray();
        asort($actual);

        $this->assertEqual($expected, $actual);
      }
    }
  }

  /**
   * Read and parse test data from a specified file.
   *
   * This method reads and parses test data from a file. The file is expected
   * to have the following structure
   *
   * ```
   * <?php
   * // PHP code goes here.
   * ~~~~~~~~~~
   * {
   *   // JSON dictionary containing expected results from testing method.
   * }
   * ```
   *
   * @param  string                 The path to the test file.
   * @return pair<XHPASTTree, map>  The first element of the pair is the
   *                                `XHPASTTree` contained within the test
   *                                file. The second element of the pair is the
   *                                "expect" data.
   */
  private function readTestData($file) {
    $contents = Filesystem::readFile($file);
    $contents = preg_split('/^~{10}$/m', $contents);

    if (count($contents) < 2) {
      throw new Exception(
        pht(
          "Expected '%s' separating test case and results.",
          '~~~~~~~~~~'));
    }

    list($data, $expect) = $contents;

    $tree = XHPASTTree::newFromData($data);
    $expect = phutil_json_decode($expect);

    return [$tree, $expect];
  }

}
