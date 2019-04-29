<?php

/**
 * @todo Remove this after https://secure.phabricator.com/D13535 has landed.
 */
final class ArcanistOneStatementPerLineXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 94;

  public function getLintName() {
    return pht('One Statement Per Line');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $statement_lists = $root->selectDescendantsOfType('n_STATEMENT_LIST');

    foreach ($statement_lists as $id => $statement_list) {
      $mapping    = [];
      $statements = $statement_list->selectDescendantsOfType('n_STATEMENT');

      // If the statement list contains //any// inline HTML, just give up...
      // XHPAST doesn't do a great job of parsing inline HTML and its usage is
      // exceedingly rare anyway. See some discussion in D28106.
      //
      // TODO: It would be nice to handle this properly eventually, but doing
      // so is fairly low priority as using inline HTML within a PHP script is
      // rather uncommon (PHP is not a great templating language). To handle
      // this correctly will most likely require changes to XHPAST.
      if (count($statement_list->selectDescendantsOfType('n_INLINE_HTML'))) {
        continue;
      }

      foreach ($statements as $statement) {
        $parent_statement_list = $this->getFirstParentOfType(
          $statement,
          'n_STATEMENT_LIST');

        // If this statement is a child of a different statement list then
        // don't do anything because the "one-statement-per-line" rule is
        // constrained to statements within the same statement list. This is
        // necessary because statement lists can be nested.
        if ($parent_statement_list != $statement_list) {
          continue;
        }

        $line_number = $statement->getLineNumber();

        if (!isset($mapping[$line_number])) {
          $mapping[$line_number] = [];
        }

        $mapping[$line_number][] = $statement;
      }

      foreach ($mapping as $line_number => $statements) {
        if (count($statements) > 1) {
          $indentation = head($statements)->getIndentation();

          // If the statements appear on the same line as the statement list
          // then it is necessary to force the first statement onto a new line.
          // For example, we don't need to handle the first statement in the
          // case of  `foo(); bar(); baz();` but we do need to handle the first
          // statement in the case of `function () { foo(); bar(); baz(); };`.
          if ($line_number == $statement_list->getLineNumber()) {
            array_unshift($statements, $statement_list);
          }

          array_shift($statements);

          foreach ($statements as $statement) {
            $this->raiseLintAtNode(
              $statement,
              pht('Each statement should appear on a separate line.'),
              "\n".$indentation.$statement->getConcreteString());
          }
        }
      }
    }
  }

  /**
   * Find the first parent node which is of the specified type.
   *
   * Given a node, return the first parent node which is of the specified type.
   *
   * @param  XHPASTNode  The input node.
   * @param  string      The node type to search for.
   * @return XHPASTNode  The first parent node of the specified type. `null` if
   *                     no such node exists.
   */
  private function getFirstParentOfType(XHPASTNode $node, $parent_type) {
    while ($node) {
      $parent = $node->getParentNode();

      if ($parent->getTypeName() == $parent_type) {
        return $parent;
      }

      $node = $parent;
    }

    return null;
  }

}
