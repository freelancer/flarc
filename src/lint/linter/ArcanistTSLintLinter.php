<?php

final class ArcanistTSLintLinter extends ArcanistBatchExternalLinter {

  private $config;
  private $project;

  public function getInfoName(): string {
    return 'TSLint';
  }

  public function getInfoURI(): string {
    return 'https://palantir.github.io/tslint/';
  }

  public function getInfoDescription(): string {
    return pht(
      'TSLint is an extensible static analysis tool that checks TypeScript '.
      'code for readability, maintainability, and functionality errors.');
  }

  public function getLinterName(): string {
    return 'TSLint';
  }
  public function getLinterConfigurationName(): string {
    return 'tslint';
  }

  public function getDefaultBinary(): string {
    return 'tslint';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^(?<version>\d+(?:\.\d+){2})$/';

    if (!preg_match($regex, $stdout, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  public function getInstallInstructions(): string {
    return pht(
      'Install %s with `%s`.',
      'TSLint',
      'yarn global add tslint typescript');
  }

  public function getUpdateInstructions(): string {
    return pht(
      'Update %s with `%s`.',
      'TSLint',
      'yarn global update tslint typescript');
  }

  protected function getMandatoryFlags(): array {
    $options = [];

    $options[] = '--format';
    $options[] = 'json';

    if ($this->config) {
      $options[] = '--config';
      $options[] = $this->config;
    }

    if ($this->project) {
      $options[] = '--project';
      $options[] = $this->project;
    }

    return $options;
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'tslint.config' => [
        'type' => 'optional string',
        'help' => pht('%s configuration file.', 'TSLint'),
      ],
      'tslint.project' => [
        'type' => 'optional string',
        'help' => pht('%s project file.', 'TSLint'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'tslint.config':
        $this->config = $value;
        return;

      case 'tslint.project':
        $this->project = $value;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    $errors = [];

    if (strlen($stderr)) {
      throw new RuntimeException(
        pht("`%s` returned an error:\n\n%s", 'tslint', $stderr));
    }

    try {
      $errors = phutil_json_decode($stdout);
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

    foreach ($errors as $error) {
      $position = idx($error, 'startPosition');

      $message = id(new ArcanistLintMessage())
        ->setPath($error['name'])
        ->setLine($position['line'])
        ->setChar(idx($position, 'character'))
        ->setCode($error['ruleName'])
        ->setDescription(idx($error, 'failure'));

      switch (idx($error, 'ruleSeverity')) {
        case 'WARNING':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
          break;

        case 'ERROR':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
          break;

        default:
          // This shouldn't be reached, but just in case...
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
          break;
      }

      if (idx($error, 'fatal', false)) {
        $message->setCode('fatal');
        $message->setName('TSLint Fatal');
      } else {
        $message->setName('TSLint '.idx($error, 'ruleName'));
      }

      $messages[] = $message;

    }
    return $messages;
  }

}
