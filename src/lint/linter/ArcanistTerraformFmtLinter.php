<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistTerraformFmtLinter extends ArcanistExternalLinter {

  const LINT_SYNTAX_ERROR = 1;
  const LINT_FORMATTING   = 2;

  public function getInfoName() {
    return 'Terraform Format';
  }

  public function getInfoURI() {
    return 'https://www.terraform.io/';
  }

  public function getInfoDescription() {
    return pht(
      '`%s` rewrites all %s configuration files to a canonical format.',
      'terraform fmt',
      'Terraform');
  }

  public function getLinterName() {
    return 'TERRAFORMFMT';
  }

  public function getLinterConfigurationName() {
    return 'terraform-fmt';
  }

  public function getLintSeverityMap() {
    return [
      self::LINT_SYNTAX_ERROR => ArcanistLintSeverity::SEVERITY_ERROR,
      self::LINT_FORMATTING   => ArcanistLintSeverity::SEVERITY_WARNING,
    ];
  }

  public function getLintNameMap() {
    return [
      self::LINT_SYNTAX_ERROR => pht('Syntax Error'),
      self::LINT_FORMATTING   => pht('Formatting Issue'),
    ];
  }

  public function getDefaultBinary() {
    return 'terraform';
  }

  public function getInstallInstructions() {
    return pht(
      'Download and install %s from %s.',
      'Terraform',
      'https://www.terraform.io/downloads.html');
  }

  protected function getMandatoryFlags() {
    return [
      'fmt',
      '-list=false',
      '-write=false',
    ];
  }

  public function getVersion() {
    list($stdout) = execx(
      '%C version',
      $this->getExecutableCommand());

    $matches = null;
    $regex = '/^Terraform v(?P<version>\d+\.\d+\.\d+)$/';

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
    // TODO: The output from `terraform fmt` is colorized. Ideally we would be
    // able to pass `-color=false` to `terraform fmt` to produce raw output,
    // but for now we just strip out the ANSI color codes from the output.
    // See https://github.com/hashicorp/terraform/issues/6926.
    $stderr = preg_replace(
      '/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[m|K]/',
      '',
      $stderr);

    if ($err) {
      $matches = null;
      $regex = pregsprintf(
        '%R%R%R%R',
        '',
        '^Error running fmt: ',
        'In (?P<path>.*?): ',
        '(At (?P<line>\d+):(?P<column>\d+): )?',
        '(?P<message>.*)$');

      if (!preg_match($regex, rtrim($stderr), $matches)) {
        throw new Exception(
          pht(
            'Failed to parse `%s` output: "%s".',
            'terraform fmt',
            $stderr));
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

    $original_file  = $this->getData($path);
    $formatted_file = $stdout;

    // `terraform fmt` removes all trailing newline characters, but we enforce
    // this elsewhere (in `ArcanistTextLinter`). Just ignore trailing newline
    // characters to prevent conflicting linter rules.
    $formatted_file = rtrim($formatted_file);
    $original_file  = rtrim($original_file);

    if ($original_file != $formatted_file) {
      $code = self::LINT_FORMATTING;

      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine(1)
        ->setChar(1)
        ->setCode($this->getLintMessageFullCode($code))
        ->setSeverity($this->getLintMessageSeverity($code))
        ->setName($this->getLintMessageName($code))
        ->setDescription(
          pht(
            'Source code is not formatted as per `%s`.',
            'terraform fmt'))
        ->setOriginalText($original_file)
        ->setReplacementText($formatted_file);

      return [$message];
    }

    return [];
  }

}
