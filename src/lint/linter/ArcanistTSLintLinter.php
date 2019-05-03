<?php

final class ArcanistTSLintLinter extends ArcanistBatchExternalLinter {

  private $config;
  private $project;

  public function getInfoName() {
    return 'TSLint';
  }

  public function getInfoURI() {
    return 'https://palantir.github.io/tslint/';
  }

  public function getInfoDescription() {
    return pht(
      'TSLint is an extensible static analysis tool that checks TypeScript '.
      'code for readability, maintainability, and functionality errors.');
  }

  public function getLinterName() {
    return 'TSLint';
  }
  public function getLinterConfigurationName() {
    return 'tslint';
  }

  public function getDefaultBinary() {
    return 'tslint';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());
    return $stdout;
  }

  public function getInstallInstructions() {
    return pht(
      'Install %s with `%s`.',
      'TSLint',
      'yarn global add tslint typescript');
  }

  public function getUpdateInstructions() {
    return pht(
      'Update %s with `%s`.',
      'TSLint',
      'yarn global update tslint typescript');
  }

  protected function getMandatoryFlags() {
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

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
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
          "`%s` returned unparseable output:\n\n%s\n%s",
          'tslint',
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
