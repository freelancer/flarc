<?php

final class ArcanistYAMLLintLinter extends ArcanistExternalLinter {

  /** @var string */
  private $config;

  public function getInfoName(): string {
    return 'yamllint';
  }

  public function getInfoUrl(): string {
    return 'https://yamllint.readthedocs.io';
  }

  public function getInfoDescription(): string {
    return pht('A linter for YAML files.');
  }

  public function getLinterName(): string {
    return 'yamllint';
  }

  public function getLinterConfigurationName(): string {
    return 'yamllint';
  }

  public function getDefaultBinary(): string {
    return 'yamllint';
  }

  public function getInstallInstructions(): string {
    return pht(
      'See %s for installation instructions.',
      'https://yamllint.readthedocs.io/en/stable/quickstart.html#installing-yamllint');
  }

  protected function getDefaultMessageSeverity($code): string {
    switch ($code) {
      case 'warning':
        return ArcanistLintSeverity::SEVERITY_WARNING;

      case 'error':
        return ArcanistLintSeverity::SEVERITY_ERROR;

      default:
        return ArcanistLintSeverity::SEVERITY_ADVICE;
    }
  }

  public function getVersion(): ?string {
    list($stdout, $stderr) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^yamllint (?P<version>\d+\.\d+\.\d+)$/';

    if (!preg_match($regex, $stderr, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  protected function getMandatoryFlags(): array {
    $flags = [];
    if ($this->config !== null) {
      $flags[] = '--config-file='.$this->config;
    }
    $flags[] = '--format=parsable';

    return $flags;
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'yamllint.config' => [
        'type' => 'optional string',
        'help' => pht('The path to a %s configuration file', 'yamllint'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'yamllint.config':
        $this->config = $value;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    $errors = [];
    $messages = [];

    $regex = '/^(?P<file>.*?):(?P<line>\d+):(?P<column>\d+):\s+'.
      '\[(?P<severity>.*)\]\s+(?P<message>.*?)(\((?P<code>\S*)\))?$/m';
    preg_match_all($regex, $stdout, $errors, PREG_SET_ORDER);

    foreach ($errors as $error) {
      $severity = $this->getLintMessageSeverity($error['severity']);

      $messages[] = (new ArcanistLintMessage())
        ->setPath($error['file'])
        ->setLine($error['line'])
        ->setChar($error['column'])
        ->setCode(idx($error, 'code', $this->getLinterName()))
        ->setName($error['message'])
        ->setDescription($error['message'])
        ->setSeverity($severity);
    }

    return $messages;
  }

}
