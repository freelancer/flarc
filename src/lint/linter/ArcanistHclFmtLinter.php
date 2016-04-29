<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistHclFmtLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'hclfmt';
  }

  public function getInfoURI() {
    return 'https://github.com/fatih/hclfmt';
  }

  public function getInfoDescription() {
    return pht(
      '`%s` is a command to format and prettify HCL files.',
      'hclfmt');
  }

  public function getLinterName() {
    return 'HCLFMT';
  }

  public function getLinterConfigurationName() {
    return 'hclfmt';
  }

  public function getDefaultBinary() {
    return 'hclfmt';
  }

  public function getVersion() {
    list($stdout) = execx(
      '%C -version',
      $this->getExecutableCommand());

    $matches = null;
    if (!preg_match('/^(?P<version>\d+\.\d+\.\d+)$/', $stdout, $matches)) {
      return false;
    }

    return $matches['version'];
  }

  public function getInstallInstructions() {
    return pht(
      'Install `%s` with `%s`.',
      'hclfmt',
      'go get github.com/fatih/hclfmt');
  }

  public function getUpdateInstructions() {
    return pht(
      'Update `%s` with `%s`.',
      'hclfmt',
      'go get -u github.com/fatih/hclfmt');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    if ($err) {
      $description = $stderr;
      $line = null;
      $column = null;

      $matches = null;
      $regex = '/^At (?P<line>\d+):(?P<column>\d+):\s+(?P<message>.*)$/';

      if (preg_match($regex, $stderr, $matches)) {
        $description = $matches['message'];
        $line = $matches['line'];
        $column = $matches['column'];
      }

      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine($line)
        ->setChar($column)
        ->setCode('SYNTAX')
        ->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR)
        ->setName(pht('Syntax Error'))
        ->setDescription($description);

      return array($message);
    }

    $original_file  = $this->getData($path);
    $formatted_file = $stdout;

    // `hclfmt` removes trailing newline characters, but we enforce this
    // elsewhere (in `ArcanistTextLinter`). Just ignore trailing newline
    // characters to prevent conflicting linter rules.
    $original_file = rtrim($original_file);

    if ($original_file != $formatted_file) {
      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine(1)
        ->setChar(1)
        ->setCode('FORMAT')
        ->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING)
        ->setName(pht('Formatting Issue'))
        ->setDescription(
          pht(
            'Source code is not formatted as per `%s`.',
            'hclfmt'))
        ->setOriginalText($original_file)
        ->setReplacementText($formatted_file);

      return array($message);
    }

    return array();
  }

}
