<?php

final class FlarcFunctionDeclarationXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1033;

  public function getLintName(): string {
    return pht('Function/Method Declaration Format');
  }

  public function getLintSeverity(): string {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $declarations = $root->selectDescendantsOfTypes([
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ]);

    foreach ($declarations as $declaration) {
      $original_function_declaration = $declaration->getConcreteString();
      $rectified_function_declaration = $this->getFormattedDeclaration($declaration);

      $matches = [];
      if (preg_match('/^(.*?)\{/', $original_function_declaration, $matches) &&
        !empty($matches) &&
        trim($matches[1]) != $rectified_function_declaration) {

        // non-greedy match the first `{` met
        $this->raiseLintMessage(
          $declaration,
          trim($matches[1]),
          $rectified_function_declaration);
      } else if (preg_match('/^(.*)\)$/', $original_function_declaration, $matches) &&
        !empty($matches) &&
        $matches[0] != $rectified_function_declaration) {

        // function declared but not defined, which end with `;`
        $this->raiseLintMessage(
          $declaration,
          $matches[0],
          $rectified_function_declaration);
      }
    }
  }

  private function getNodeString(XHPASTNode $node): string {
    $concrete = $node->getConcreteString();
    if ($node->getTypeName() === 'n_NULLABLE_TYPE') {
      $concrete = '?'.$concrete;
    }
    if ($node->getTypeName() === 'n_DECLARATION_RETURN' &&
      $node->getChildByIndex(0)->getTypeName() === 'n_NULLABLE_TYPE') {

      $concrete = '?'.$concrete;
    }
    return $concrete;
  }

  private function getFormattedDeclaration(XHPASTNode $declaration): string {
    $function_declaration = 'function ';

    // modifiers such as `abstract`, `static`, `public`...
    $function_declaration = sprintf(
      '%s %s',
      implode(
        ' ',
        array_map(
          [$this, 'getNodeString'],
          $declaration->getChildByIndex(0)->getChildren())),
      $function_declaration);

    // Reference '&'
    $function_declaration .=
     ltrim($declaration->getChildByIndex(1)->getConcreteString().' ');

    // Function name
    $function_declaration .=
     $declaration->getChildByIndex(2)->getConcreteString();

    // Parameter declaration
    $function_declaration .=
      $this->extractParameterDeclaration($declaration->getChildByIndex(3));

    // `use` declaration
    $function_declaration .=
      $this->extractUseDeclaration($declaration->getChildByIndex(4));

    // Return type declaration
    $function_declaration .= rtrim(
      sprintf(
        ': %s',
        $this->getNodeString($declaration->getChildByIndex(5))),
      ': ');
    return trim($function_declaration);
  }

  private function extractParameterDeclaration(XHPASTNode $node): string {
    $chunks = array_map(
      function (XHPASTNode $child_node): string {
        $chunks = array_map(
          [$this, 'getNodeString'],
          $child_node->getChildren());

        // Reindex, so that the index start from 0.
        $chunks = array_values($chunks);
        return trim(
          sprintf('%s %s = %s', $chunks[0], $chunks[1], $chunks[2]),
          ' = ');
      },
      $node->getChildren());
    return sprintf('(%s)', implode(', ', $chunks));
  }

  private function extractUseDeclaration(XHPASTNode $node): string {
    if ($node->getTypeName() == 'n_EMPTY') {
      return '';
    } else {
      return sprintf(
        ' use (%s)',
        implode(
          ', ',
          array_map([$this, 'getNodeString'], $node->getChildren())));
    }
  }

  private function raiseLintMessage(
    XHPASTNode $declaration,
    $original_function_declaration,
    $rectified_function_declaration) {

    $this->raiseLintAtOffset(
      $declaration->getOffset(),
      pht('Improper function/method declaration format'),
      $original_function_declaration,
      $rectified_function_declaration);
  }
}
