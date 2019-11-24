<?php

final class ArcanistPsalmLinter extends ArcanistBatchExternalLinter {

  private $config;
  private $future;

  public function getInfoName(): string {
    return pht('Psalm');
  }

  public function getInfoUrl(): string {
    return 'https://psalm.dev/';
  }

  public function getInfoDescription(): string {
    return pht(
      'Psalm is a static analysis tool that\'s designed to improve large PHP '.
      'codebases by identifying both obvious and hard-to-spot bugs.');
  }

  public function getLinterName(): string {
    return 'Psalm';
  }

  public function getLinterConfigurationName(): string {
    return 'psalm';
  }

  public function getDefaultBinary(): string {
    return 'psalm';
  }

  public function shouldUseInterpreter(): bool {
    return true;
  }

  public function getDefaultInterpreter(): ?string {
    return null;
  }

  public function getInstallInstructions(): string {
    return pht('See %s.', 'https://psalm.dev/docs/running_psalm/installation/');
  }

  public function shouldExpectCommandErrors(): bool {
    return true;
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^Psalm (?<version>\d+(?:\.\d+){2})@(?<git_commit>[a-z0-9]+)$/';

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
      '--no-progress',
      '--output-format=json',
      // TODO: tweak the number of threads based on actual performance
      '--threads=4',
    ];

    if ($this->config !== null) {
      $flags[] = '--config='.$this->config;
    }

    return $flags;
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'psalm.config' => [
        'type' => 'optional string',
        'help' => pht('The path to a %s configuration file', 'Psalm'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'psalm.config':
        $this->config = $value;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  public function getLintSeverityMap() {
    return [
      'error' => ArcanistLintSeverity::SEVERITY_ERROR,
      'info'  => ArcanistLintSeverity::SEVERITY_ADVICE,
    ];
  }

  protected function parseLinterOutput($_, $err, $stdout, $stderr): array {
    if ($err !== 0 && $err !== 1) {
      throw new CommandException(
        pht('Linter execution failed with err code %s', $err),
        $this->getLinterName(),
        $err,
        $stdout,
        $stderr);
    }

    if ($err === 0 && trim($stdout) === '') {
      // psalm outputs nothing to stdout on success, which is an invalid json
      return [];
    }

    $report = (new PhutilJSONParser())->parse($stdout);

    $messages = [];
    foreach ($report as $message) {
      $severity = $this->getLintMessageSeverity($message['severity']);
      $messages[] = (new ArcanistLintMessage())
        ->setPath($message['file_path'])
        ->setLine($message['line_from'])
        ->setChar($message['column_from'])
        ->setCode('Psalm')
        ->setName($message['type'])
        ->setDescription($message['message'])
        ->setSeverity($severity);
    }
    return $messages;
  }

}
