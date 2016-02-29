<?php

/**
 * A base class for linter rules which affect docblock comments.
 *
 * @task parse  Parsing
 */
abstract class ArcanistDocblockCommentXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  private $parser;

  public function __construct() {
    $this->parser = new PhutilDocblockParser();
  }

  public function getLintSeverity() {
    // Linter violations of this type are typically fairly benign.
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }


/* -(  Parsing  )------------------------------------------------------------ */


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
   * @param  XHPASTToken                        The docblock comment token.
   * @return list<string, string, map, string>  Fields of the comment, in the
   *                                            form of `list<$summary, $description, $tags, $raw_text`.
   *
   * @task parse
   */
  final protected function parse(XHPASTToken $token) {
    if ($token->getTypeName() != 'T_DOC_COMMENT') {
      throw new Exception(
        pht(
          'Expected `%s` of type `%s`.',
          'XHPASTToken',
          'T_DOC_COMMENT'));
    }

    // We retain the raw text because `PhutilDocblockParser` throws away leading
    // and trailing whitespace, and performs various other normalizations.
    $raw_text = $token->getValue();
    list($text, $tags) = $this->parser->parse($raw_text);

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

}
