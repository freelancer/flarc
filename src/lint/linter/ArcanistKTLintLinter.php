<?php

final class ArcanistKTLintLinter extends ArcanistExternalLinter {

  const LINT_SYNTAX_ERROR = 1;
  const LINT_FORMATTING   = 2;

  public function getInfoName() {
    return 'KTLint Format';
  }

  public function getInfoURI() {
    return 'https://github.com/shyiko/ktlint/';
  }

  public function getInfoDescription() {
    return pht(
      '`%s` rewrites all %s files to a canonical format.',
      'ktlint -F',
      'Kotlin');
  }

  public function getLinterName() {
    return 'KTLINT';
  }

  public function getLinterConfigurationName() {
    return 'ktlint';
  }

  public function getLintSeverityMap() {
    return [
      self::LINT_SYNTAX_ERROR => ArcanistLintSeverity::SEVERITY_ERROR,
      self::LINT_FORMATTING   => ArcanistLintSeverity::SEVERITY_AUTOFIX,
    ];
  }

  public function getLintNameMap() {
    return [
      self::LINT_SYNTAX_ERROR => pht('Syntax Error'),
      self::LINT_FORMATTING   => pht('Formatting Issue'),
    ];
  }

  public function getDefaultBinary() {
    return 'ktlint';
  }

  public function getInstallInstructions() {
    return pht(
      'Download and install %s from %s.',
      'ktlint',
      'https://github.com/shyiko/ktlint');
  }

  protected function getMandatoryFlags() {
    return [];
  }

  public function getVersion() {
    list($stdout) = execx(
      '%C --version',
      $this->getExecutableCommand());

    $matches = null;
    $regex = '/^(?P<version>\d+\.\d+\.\d+)$/';

    if (!preg_match($regex, rtrim($stdout), $matches)) {
      return false;
    }

    return $matches['version'];
  }

  /**
   * Get a version string used for caching lint results.
   *
   * The implementation of this method was mostly copied from
   * @{method:ArcanistExternalLinter:getCacheVersion} but was adapted in order
   * to ensure that only flags which actually affect the linter results are
   * used within the lint cache key.
   *
   * @return string
   */
  public function getCacheVersion() {
    try {
      $this->checkBinaryConfiguration();
    } catch (ArcanistMissingLinterException $e) {
      return null;
    }

    return $this->getVersion();
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    if ($err) {
      $matches = null;
      $regex = pregsprintf(
        '%R%R%R',
        '',
        '^(?P<path>.*?):',
        '(?P<line>\d+):(?P<column>\d+):',
        '(?P<message>.*)$');

      if (!preg_match($regex, rtrim($stdout), $matches)) {
        throw new Exception(
          pht(
            'Failed to parse `%s` output: "%s".',
            'ktlint',
            $stdout));
      }

      $code = self::LINT_SYNTAX_ERROR;
      $line   = nonempty((int)$matches['line'], null);
      $column = nonempty((int)$matches['column'], null);

      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine($line)
        ->setChar($column)
        ->setCode($this->getLintMessageFullCode($code))
        ->setSeverity($this->getLintMessageSeverity($code))
        ->setName($this->getLintMessageName($code))
        ->setDescription($matches['message']);

      return [$message];
    }

    return [];
  }

}
