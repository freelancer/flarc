<?php

final class ArcanistPHPCSFixerLinter extends ArcanistBatchExternalLinter {
  private $config;

  /**
   * See https://github.com/FriendsOfPHP/PHP-CS-Fixer#exit-codes for reference
   * return codes that do not indicate error are ignored as they are not 'error code'.
   *
   * @var array
   */
  private $errCodeMsg = [
    1 => 'General error (or PHP minimal requirement not matched).',
    4 => 'Some files have invalid syntax (only in dry-run mode).',
    16 => 'Configuration error of the application.',
    32 => 'Configuration error of a Fixer.',
    64 => 'Exception raised within the application.',
  ];

  public function getInfoName(): string {
    return pht('php-cs-fixer');
  }

  public function getInfoUrl(): string {
    return 'https://github.com/FriendsOfPHP/PHP-CS-Fixer';
  }

  public function getInfoDescription(): string {
    return pht('The PHP Coding Standards Fixer (PHP CS Fixer) tool fixes your code to follow standards;');
  }

  public function getLinterName(): string {
    return 'php-cs-fixer';
  }

  public function getLinterConfigurationName(): string {
    return 'php-cs-fixer';
  }

  public function getDefaultBinary(): string {
    return 'php-cs-fixer';
  }

  public function getInstallInstructions(): string {
    return pht('See %s.', 'https://github.com/FriendsOfPHP/PHP-CS-Fixer#installation');
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'php-cs-fixer.config' => [
        'type' => 'string',
        'help' => pht('The path to a %s configuration file', 'php-cs-fixer'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'php-cs-fixer.config':
        $this->config = $value;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  public function shouldExpectCommandErrors(): bool {
    return true;
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    if (!preg_match('/(?P<version>\d+\.\d+\.\d+)/', $stdout, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  protected function shouldLintDirectories(): bool {
    return true;
  }

  protected function getMandatoryFlags(): array {
    return [
      'fix',
      '--config='.$this->config,
      '--diff',
      '--diff-format=udiff',
      '--dry-run',
      '--format=json',
      '-vvv',
    ];
  }

  protected function parseLinterOutput($_, $err, $stdout, $stderr) {
    if (isset($this->errCodeMsg[$err])) {
      throw new CommandException(
        pht('%s', $this->errCodeMsg[$err]),
        $this->getLinterName(),
        $err,
        $stdout,
        $stderr);
    }

    $report = (new PhutilJSONParser())->parse($stdout);
    $messages = [];

    foreach ($report['files'] as $file) {
      $hunks = (new FlarcDiffParser())->parseDiff($file['diff']);

      foreach ($hunks as $hunk) {
        $messages[] = (new ArcanistLintMessage())
          ->setPath($file['name'])
          ->setLine($hunk->getLine())
          ->setChar(1) // first char in the line
          ->setCode('PHP-CS-Fixer')
          ->setName('PHP-CS-Fixer')
          ->setDescription(sprintf('(%s) '.
            'Please consider the following changes', implode(', ', $file['appliedFixers'])))
          ->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE)
          ->setBypassChangedLineFiltering(true)
          ->setOriginalText($hunk->getOriginal())
          ->setReplacementText($hunk->getReplacement());
      }
    }
    return $messages;
  }
}
