<?php

final class ArcanistJenkinsfileLintLinter extends ArcanistExternalLinter {
  /** @var string */
  private $config;

  public function getInfoName(): string {
    return 'jenkinsfile';
  }

  public function getInfoUrl(): string {
    return 'https://jenkins.io/doc/book/pipeline/development/#linter';
  }

  public function getInfoDescription(): string {
    return pht('A linter for YAML files.');
  }

  public function getLinterName(): string {
    return 'jenkinsfile';
  }

  public function getLinterConfigurationName(): string {
    return 'jenkinsfile';
  }

  public function getDefaultBinary(): string {
    return 'bin/jenkinsfile';
  }

  public function getInstallInstructions(): string {
    return pht(
      'See %s for installation instructions.',
      'https://jenkins.io/doc/book/pipeline/development/#linter');
  }

  protected function getDefaultMessageSeverity($code): string {
    switch ($code) {
      default:
        return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  public function getVersion(): ?string {
    return null;
  }

  protected function getMandatoryFlags(): array {
    return [];
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    $errors = [];
    $messages = [];
    $regex = '/WorkflowScript:\s+\d+:\s+(?P<message>.*?)\s+@\s+line\s+(?P<line>\d+),\s+column\s+(?P<column>\d+)\./';
    preg_match_all($regex, $stdout, $errors, PREG_SET_ORDER);

    foreach ($errors as $error) {
      $severity = $this->getLintMessageSeverity('');

      $messages[] = (new ArcanistLintMessage())
        ->setPath($path)
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
