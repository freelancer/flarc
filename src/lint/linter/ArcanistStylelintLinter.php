<?php

final class ArcanistStylelintLinter extends ArcanistExternalLinter {

  private $config;

  public function getInfoName() {
    return 'stylelint';
  }

  public function getInfoURI() {
    return 'http://stylelint.io/';
  }

  public function getInfoDescription() {
    return pht(
      'A mighty, modern %s linter that helps you enforce consistent '.
      'conventions and avoid errors in your stylesheets.',
      'CSS');
  }

  public function getLinterName() {
    return 'stylelint';
  }

  public function getLinterConfigurationName() {
    return 'stylelint';
  }

  public function getDefaultBinary() {
    return 'stylelint';
  }

  public function getVersion() {
    list($stdout) = execx(
      '%C --version',
      $this->getExecutableCommand());

    $matches = null;
    if (!preg_match('/^(?P<version>\d+\.\d+\.\d+)$/', $stdout, $matches)) {
      return false;
    }

    return $matches['version'];
  }

  public function getInstallInstructions() {
    return pht(
      'Install %s with `%s`.',
      'stylelint',
      'npm install -g stylelint');
  }

  public function getUpdateInstructions() {
    return pht(
      'Update %s with `%s`.',
      'stylelint',
      'npm install -g stylelint');
  }

  protected function getMandatoryFlags() {
    $options = [];

    $options[] = '--formatter=json';
    $options[] = '--no-color';

    if ($this->config) {
      $options[] = '--config='.$this->config;
    }

    return $options;
  }

  public function getLinterConfigurationOptions() {
    $options = [
      'stylelint.config' => [
        'type' => 'optional string',
        'help' => pht('%s configuration file.', 'stylelint'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'stylelint.config':
        $this->config = $value;
        return;

      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $messages = [];

    try {
      $files = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('`%s` returned unparseable output.', 'stylelint'),
        $ex);
    }

    foreach ($files as $file) {
      foreach ($file['warnings'] as $warning) {
        $message = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($warning['line'])
          ->setChar($warning['column'])
          ->setCode($warning['rule'])
          ->setName($warning['rule'])
          ->setDescription($warning['text']);

        switch ($warning['severity']) {
          case 'error':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;

          case 'warning':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            break;

          default:
            // This shouldn't be reached, but just in case...
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
            break;
        }

        $messages[] = $message;
      }
    }

    return $messages;
  }
}
