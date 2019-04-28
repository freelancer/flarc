<?php

/**
 * @todo Submit the methods contained within this class upstream (to the
 *       `ArcanistXHPASTLinterRule` class) after T27678.
 */
abstract class FlarcXHPASTLinterRule extends ArcanistXHPASTLinterRule {

  /**
   * Parse a docblock comment.
   *
   * Parses an @{class:XHPASTToken} of type `T_DOC_COMMENT` into the following
   * fields:
   *
   *   - **Summary:** The first section of the body of the docblock comment is
   *     the summary field. See
   *     https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md#user-content-51-summary.
   *   - **Description:** The remaining sections of the body of the comment
   *     (i.e. excluding the summary section) comprise the description. See
   *     https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md#user-content-52-description.
   *   - **Tags:** Tags provide a way for authors to supply concise meta-data
   *     regarding the succeeding structural element. See
   *     https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md#user-content-53-tags.
   *   - **Raw text:** The raw text from the docblock comment.
   *
   * If an @{class:XHPASTToken} which is of a type other than `T_DOC_COMMENT`
   * is passed to this method then an exception will be thrown.
   *
   * @param  XHPASTToken                     The docblock comment token.
   * @return list<string,string,map,string>  Fields of the comment, in the
   *                                         form of `list<$summary, $description, $tags, $raw_text>`.
   */
  final protected function parseDocblock(XHPASTToken $token) {
    $parser = new PhutilDocblockParser();

    if ($token->getTypeName() != 'T_DOC_COMMENT') {
      throw new Exception(
        pht(
          'Expected `%s` of type `%s`.',
          'XHPASTToken',
          'T_DOC_COMMENT'));
    }

    // We retain the raw text because `PhutilDocblockParser` throws away
    // leading and trailing whitespace, and performs various other
    // normalizations.
    $raw_text = $token->getValue();
    list($text, $tags) = $parser->parse($raw_text);

    // According to PSR-5, a summary can end with either a full stop followed
    // by a line break, or two sequential line breaks.
    $sections = preg_split('/(\n\s*\n|(?<=\.)\n)\s*/', $text, 2);

    $summary     = $sections[0];
    $description = idx($sections, 1);

    if (!strlen($summary)) {
      $summary = null;
    }

    return array($summary, $description, $tags, $raw_text);
  }

  /**
   * Return given tag value in docblock.
   *
   * @param  XHPASTNode
   * @param  string      Tag name
   * @return string|list<string>|null
   */
  final protected function getDocblockTag(XHPASTNode $node, $tag_name) {
    $docblock = $node->getDocblockToken();
    if ($docblock === null) {
      return null;
    }
    list($_, $_, $tags) = $this->parseDocblock($docblock);
    return idx($tags, $tag_name);
  }

  /**
   * Check whether the given docblock type represents union type.
   *
   * Union type is used for values which could be multiple types. For example,
   * a value is `int|array` means the value can be `int` or `array`.
   *
   * @param  string
   * @return bool
   */
  final protected function isUnionDocblockType($type) {
    return $type === 'mixed' || strpos($type, '|') !== false;
  }

  /**
   * Check whether the given docblock type represents nullable type.
   *
   * @param  string
   * @return bool
   */
  final protected function isNullableDocblockType($type): bool {
    $types = explode('|', $type);
    return count($types) === 2 && in_array('null', $types);
  }
}
