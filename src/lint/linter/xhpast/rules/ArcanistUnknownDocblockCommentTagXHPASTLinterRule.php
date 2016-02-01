<?php

final class ArcanistUnknownDocblockCommentTagXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1017;

  public function getLintName() {
    return pht('Unknown Docblock Comment Tag');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    // Classes
    $this->lintDocblockCommentTags(
      $this->getDocblockTokensForTypes(
        $root,
        array(
          'n_CLASS_DECLARATION',
          'n_INTERFACE_DECLARATION',
        )),
      array(
        'backupGlobals',
        'backupStaticAttributes',
        'codeCoverageIgnore',
        'concrete-extensible',
        'coversDefaultClass',
        'coversNoDefaultClass',
        'coversNothing',
        'deprecated',
        'generated',
        'link',
        'requires',
        'runTestsInSeparateProcesses',
        'see',
        'task',
        'todo',
      ),
      'class');

    // Functions
    $this->lintDocblockCommentTags(
      $this->getDocblockTokensForType(
        $root,
        'n_FUNCTION_DECLARATION'),
      array(
        'codeCoverageIgnore',
        'deprecated',
        'generated',
        'link',
        'param',
        'return',
        'see',
        'task',
        'throws',
        'todo',
      ),
      'function');

    // Methods
    $this->lintDocblockCommentTags(
      $this->getDocblockTokensForType(
        $root,
        'n_METHOD_DECLARATION'),
      array(
        'backupGlobals',
        'backupStaticAttributes',
        'codeCoverageIgnore',
        'covers',
        'coversNothing',
        'dataProvider',
        'depends',
        'deprecated',
        'expectedException',
        'expectedExceptionCode',
        'expectedExceptionMessage',
        'expectedExceptionMessageRegExp',
        'generated',
        'group',
        'link',
        'param',
        'preserveGlobalState',
        'requires',
        'return',
        'runInSeparateProcess',
        'see',
        'task',
        'test',
        'throws',
        'todo',
        'uses',
      ),
      'method');

    // Properties
    $this->lintDocblockCommentTags(
      $this->getDocblockTokensForType(
        $root,
        'n_CLASS_MEMBER_DECLARATION_LIST'),
      array(
        'link',
        'see',
        'todo',
        'var',
      ),
      'property');
  }

  /**
   * Lint docblock comment tags.
   *
   * This method lints "tags" (otherwise known as "annotations") within
   * docblock comments (i.e. `XHPASTTokens` of type `T_DOC_COMMENT`) to ensure
   * that only whitelisted tags (specified by `$whitelisted_tags`) are used.
   *
   * @param  list<XHPASTToken>  Docblock comment tags to be checked.
   * @param  list<string>       List of whitelisted tags which are allowed.
   * @param  string             A string describing the docblock comment token.
   * @return void
   */
  private function lintDocblockCommentTags(
    array $tokens,
    $whitelisted_tags,
    $type) {

    $parser = new PhutilDocblockParser();

    // Storing the whitelisted tags as keys rather than values is more
    // performant as lookup becomes `O(1)` rather than `O(N)`.
    $whitelisted_tags = array_fill_keys($whitelisted_tags, true);

    assert_instances_of($tokens, 'XHPASTToken');

    foreach ($tokens as $token) {
      list(, $tags) = $parser->parse($token->getValue());

      $unknown_tags = array_keys(array_diff_key($tags, $whitelisted_tags));

      foreach ($unknown_tags as $unknown_tag) {
        $corrections = ArcanistConfiguration::correctCommandSpelling(
          $unknown_tag,
          array_keys($whitelisted_tags),
          2);
        $corrected_tag = nonempty(head($corrections), null);

        $this->raiseLintAtDocblockTag(
          $token,
          $unknown_tag,
          pht(
            "Unknown tag in %s docblock token.\n\n".
            "The specified tag (`%s`) is unknown and possibly misspelled. ".
            "Tags are whitelisted in order to be able to detect invalid ".
            "annotations. If you believe this to be in error (i.e. if you ".
            "believe that `%s` is a legitimate tag), please file a ticket ".
            "with %s.",
            $type,
            '@'.$unknown_tag,
            '@'.$unknown_tag,
            '#software_infrastructure_team'),
          $corrected_tag);
      }
    }
  }


/* -(  Utility  )------------------------------------------------------------ */


  /**
   * Get all docblock tokens for the specified node type.
   *
   * This method is a convenience wrapper around
   * @{method:getDocblockTokensForTypes}, which accepts a single type rather
   * than a list of types.
   *
   * @param  XHPASTNode         The root node.
   * @param  string             A node type.
   * @return list<XHPASTToken>  Tokens of type `T_DOC_COMMENT`.
   */
  protected function getDocblockTokensForType(XHPASTNode $root, $type) {
    return $this->getDocblockTokensForTypes($root, array($type));
  }

  /**
   * Get all docblock tokens which belong to the specified node types.
   *
   * This method is a convenience wrapper around the
   * @{method:XHPASTNode:selectDescendantsOfTypes} and
   * @{method:XHPASTNode:getDocblockToken} methods.
   *
   * @param  XHPASTNode         The root node.
   * @param  list<string>       A list of node types.
   * @return list<XHPASTToken>  Tokens of type `T_DOC_COMMENT`.
   */
  protected function getDocblockTokensForTypes(XHPASTNode $root, array $types) {
    $nodes = $root->selectDescendantsOfTypes($types);
    $tokens = mpull($nodes->getRawNodes(), 'getDocblockToken');
    return array_filter($tokens);
  }

  /**
   * Raise a linter message at the specified docblock tag.
   *
   * This method takes an @{class:XHPASTToken} of type `T_DOC_COMMENT` and a
   * "tag" (otherwise known as an "annotation") and raises a linter message at
   * the specified tag within the docblock comment. If the specified tag has
   * multiple occurrences within the docblock comment then this method will
   * fallback to raising the linter message at the entire token rather than the
   * specified tag.
   *
   * @param  XHPASTToken          A token of type `T_DOC_COMMENT`.
   * @param  string               The tag at which to raise a linter message.
   *                              The leading `@` should be omitted.
   * @param  string               The linter message.
   * @param  string|null          The replacement text.
   * @return ArcanistLintMessage  The linter message.
   */
  protected function raiseLintAtDocblockTag(
    XHPASTToken $token,
    $tag,
    $message,
    $replacement = null) {

    if ($token->getTypeName() !== 'T_DOC_COMMENT') {
      throw new Exception(
        pht(
          'Expected an `%s` of type `%s`.',
          'XHPASTToken',
          'T_DOC_COMMENT'));
    }

    // Prepend "@" to the tag to avoid matching literal text within the
    // docblock comment.
    $tag = '@'.$tag;
    if ($replacement) {
      $replacement = '@'.$replacement;
    }

    $offset = strpos($token->getValue(), $tag);
    if ($offset === false) {
      throw new Exception(
        pht(
          'Unable to find `%s` tag within docblock comment.',
          $tag));
    }

    // If the tag occurs multiple times within the docblock comment then we
    // cannot determine //which// tag to raise the linter message at. Instead,
    // raise the linter message at the entire docblock comment token instead.
    // When doing so, we can no longer use the provided replacement text either.
    if (strrpos($token->getValue(), $tag) != $offset) {
      return $this->raiseLintAtToken($token, $message);
    }

    return $this->raiseLintAtOffset(
      $token->getOffset() + $offset,
      $message,
      $tag,
      $replacement);
  }

}
