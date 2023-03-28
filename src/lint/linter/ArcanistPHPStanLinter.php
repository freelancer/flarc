<?php

final class ArcanistPHPStanLinter extends ArcanistBatchExternalLinter {

  private $config;
  private $future;

  public function getInfoName(): string {
    return pht('PHPStan');
  }

  public function getInfoUrl(): string {
    return 'https://github.com/phpstan/phpstan';
  }

  public function getInfoDescription(): string {
    return pht(
      'PHP Static Analysis Tool - discover bugs in your code '.
      'without running it!');
  }

  public function getLinterName(): string {
    return 'PHPStan';
  }

  public function getLinterConfigurationName(): string {
    return 'phpstan';
  }

  public function getDefaultBinary(): string {
    return 'phpstan';
  }

  public function shouldUseInterpreter(): bool {
    return true;
  }

  public function getDefaultInterpreter(): ?string {
    return null;
  }

  public function getInstallInstructions(): string {
    return pht('See %s.', 'https://github.com/phpstan/phpstan#installation');
  }

  public function shouldExpectCommandErrors(): bool {
    return true;
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^PHPStan - PHP Static Analysis Tool (?<version>\d+(?:\.\d+){2})$/';

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
      'analyse',
      '--no-progress',
      '--error-format=json',
      '--memory-limit=-1',
      '--no-ansi',
      '--no-interaction',
    ];

    if ($this->config !== null) {
      $flags[] = '--configuration='.$this->config;
    }

    return $flags;
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'phpstan.config' => [
        'type' => 'optional string',
        'help' => pht('The path to a %s configuration file', 'PHPStan'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'phpstan.config':
        $this->config = $value;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    if ($err !== 0 && $err !== 1) {
      throw new CommandException(
        pht(
          '%s execution failed with err code %s',
          $this->getLinterName(),
          $err),
        $this->getExecutableCommand(),
        $err,
        $stdout,
        $stderr);
    }

    try {
      $report = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      // PHPStan returns 1 when it finds errors, but also when it finds no
      // files to analyse. In this case, we return an empty array of messages.
      // TODO: Fix this when PHPStan report support no files found in v2.
      if (str_contains($stderr, 'No files found to analyse')) {
        return [];
      }

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
    foreach ($report['files'] as $file_path => $file) {
      foreach ($file['messages'] as $message) {
        $severity = $message['ignorable']
          ? $this->getLintMessageSeverity($message['message'])
          : ArcanistLintSeverity::SEVERITY_ERROR;
        $messages[] = (new ArcanistLintMessage())
          ->setPath($file_path)
          ->setLine($message['line'])
          ->setChar(0) // TODO: Fix this when PHPStan report support column
          ->setCode('PHPStan')
          ->setName('PHPStan')
          ->setDescription($message['message'])
          ->setSeverity($severity);
      }
    }

    return $messages;
  }

}
