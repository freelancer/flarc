<?php

final class FlarcJSHintLinter extends ArcanistExternalLinter {

  private $jshintignore;
  private $jshintrc;

  public function getInfoName(): string {
    return pht('JavaScript error checking');
  }

  public function getInfoURI(): string {
    return 'https://www.jshint.com';
  }

  public function getInfoDescription(): string {
    return pht(
      'Use `%s` to detect issues with JavaScript source files.',
      'jshint');
  }

  public function getLinterName(): string {
    return 'JSHint';
  }

  public function getLinterConfigurationName(): string {
    return 'fl-jshint';
  }

  protected function getDefaultMessageSeverity($code): string {
    if (preg_match('/^W/', $code)) {
      return ArcanistLintSeverity::SEVERITY_WARNING;
    } else if (preg_match('/^E043$/', $code)) {
      // TODO: If JSHint encounters a large number of errors, it will quit
      // prematurely and add an additional "Too Many Errors" error. Ideally, we
      // should be able to pass some sort of `--force` option to `jshint`.
      //
      // See https://github.com/jshint/jshint/issues/180
      return ArcanistLintSeverity::SEVERITY_DISABLED;
    } else {
      return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  public function getDefaultBinary(): string {
    return 'jshint';
  }

  public function getVersion(): ?string {
    // NOTE: `jshint --version` emits version information on stderr, not stdout.
    list($stdout, $stderr) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^jshint v(?<version>\d+(?:\.\d+){2})$/';

    if (!preg_match($regex, $stderr, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  public function getInstallInstructions(): string {
    return pht('Install JSHint using `%s`.', 'npm install -g jshint');
  }

  protected function getMandatoryFlags(): array {
    $options = [];

    $options[] = '--reporter=checkstyle';

    if ($this->jshintrc) {
      $options[] = '--config='.$this->jshintrc;
    }

    if ($this->jshintignore) {
      $options[] = '--exclude-path='.$this->jshintignore;
    }

    return $options;
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'jshint.jshintignore' => [
        'type' => 'optional string',
        'help' => pht('Pass in a custom jshintignore file path.'),
      ],
      'jshint.jshintrc' => [
        'type' => 'optional string',
        'help' => pht('Custom configuration file.'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'jshint.jshintignore':
        $this->jshintignore = $value;
        return;

      case 'jshint.jshintrc':
        $this->jshintrc = $value;
        return;
    }

    parent::setLinterConfigurationValue($key, $value);
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    $errors = simplexml_load_string($stdout);

    $messages = [];
    foreach ($errors->file as $file) {
      foreach ($file->error as $err) {
        $code = substr((string)$err['source'], strlen('jshint.'));

        $messages[] = (new ArcanistLintMessage())
          ->setPath((string)$file['name'])
          ->setLine((int)$err['line'])
          ->setChar((int)$err['column'])
          ->setCode(pht('%s', $code))
          ->setName(pht('%s', $code))
          ->setDescription(pht('%s', $err['message']))
          ->setSeverity($this->getLintMessageSeverity($code));
      }
    }

    return $messages;
  }

  protected function getLintCodeFromLinterConfigurationKey($code): string {
    if (!preg_match('/^(E|W)\d+$/', $code)) {
      throw new Exception(
        pht(
          'Unrecognized lint message code "%s". Expected a valid JSHint '.
          'lint code like "%s" or "%s".',
          $code,
          'E033',
          'W093'));
    }

    return $code;
  }

}
