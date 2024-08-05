<?php

final class ArcanistPHPCSFixerLinter extends ArcanistBatchExternalLinter {
  private $config;

  /**
   * See https://github.com/FriendsOfPHP/PHP-CS-Fixer#exit-codes for reference
   * return codes that do not indicate error are ignored as they are not an
   * error code.
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
    return pht(
      'The PHP Coding Standards Fixer (PHP CS Fixer) tool '.
      'fixes your code to follow standards.');
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

  public function shouldUseInterpreter(): bool {
    return true;
  }

  public function getDefaultInterpreter(): ?string {
    return null;
  }

  public function getInstallInstructions(): string {
    return pht(
      'See %s.',
      'https://github.com/FriendsOfPHP/PHP-CS-Fixer#installation');
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'interpreter' => [
        'type' => 'optional string',
        'help' => pht('The PHP interpreter to use'),
      ],
      'php-cs-fixer.config' => [
        'type' => 'optional string',
        'help' => pht('The path to a %s configuration file', 'php-cs-fixer'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'php-cs-fixer.config':
        $this->config = $value;
        break;

      default:
        try {
            parent::setLinterConfigurationValue($key, $value);
        } catch (Exception $e) {
          $message = <<<DOC
{$e->getMessage()}

FIX: Try 'composer install'

DOC;

          throw new Exception(
            $message,
            $e->getCode(),
            $e
          );
        }
        break;
    }
  }

  public function shouldExpectCommandErrors(): bool {
    return true;
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^PHP CS Fixer (?<version>\d+(?:\.\d+){2})\b/';

    if (!preg_match($regex, $stdout, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  protected function shouldLintDirectories(): bool {
    return true;
  }

  protected function getMandatoryFlags(): array {
    $flags = [
      'fix',
      '--diff',
      '--dry-run',
      '--format=json',
      '-vvv',
    ];

    if ($this->config !== null) {
      $flags[] = '--config='.$this->config;
    }

    return $flags;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    if (isset($this->errCodeMsg[$err])) {
      throw new CommandException(
        pht('%s', $this->errCodeMsg[$err]),
        $this->getLinterName(),
        $err,
        $stdout,
        $stderr);
    }

    try {
      $report = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          "Failed to parse `%s` output. Expecting valid JSON.\n\n".
          "Exception:\n%s\n\nSTDOUT\n%s\n\nSTDERR\n%s",
          $this->getLinterConfigurationName(),
          $ex->getMessage(),
          $stdout,
          $stderr),
        $ex);
    }

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
          ->setDescription(
            pht(
              '(%s) Please consider the following changes',
              implode(', ', $file['appliedFixers'])))
          ->setSeverity(ArcanistLintSeverity::SEVERITY_AUTOFIX)
          ->setBypassChangedLineFiltering(true)
          ->setOriginalText($hunk->getOriginal())
          ->setReplacementText($hunk->getReplacement());
      }
    }

    return $messages;
  }

}
