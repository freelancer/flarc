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
    $statements = $root->selectDescendantsOfType('n_STATEMENT');
    $mapping = array();

    foreach ($statements as $statement) {
      $line_number = $statement->getLineNumber();

      if (!isset($mapping[$line_number])) {
        $mapping[$line_number] = array();
      }

      $mapping[$line_number][] = $statement;
    }

    foreach ($mapping as $line_number => $statements) {
      if (count($statements) > 1) {
        $first_statement = array_shift($statements);

        foreach ($statements as $statement) {
          $this->raiseLintAtNode(
            $statement,
            pht('Only one statement per line.'),
            "\n".$first_statement->getIndentation().
            $statement->getConcreteString());
        }
      }
    }
  }

}
