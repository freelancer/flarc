<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistESLintLinter extends ArcanistExternalLinter {

  private $config;
  private $env;

  public function getInfoName() {
    return 'ESLint';
  }

  public function getInfoURI() {
    return 'http://eslint.org/';
  }

  public function getInfoDescription() {
    return pht(
      'The pluggable linting utility for %s and %s.',
      'JavaScript',
      'JSX');
  }

  public function getLinterName() {
    return 'ESLint';
  }

  public function getLinterConfigurationName() {
    return 'eslint';
  }

  public function getDefaultBinary() {
    return 'eslint';
  }

  public function getVersion() {
    list($stdout) = execx(
      '%C --version',
      $this->getExecutableCommand());

    $matches = null;
    if (!preg_match('/^v(?P<version>\d+\.\d+\.\d+)$/', $stdout, $matches)) {
      return false;
    }

    return $matches['version'];
  }

  public function getInstallInstructions() {
    return pht(
      'Install %s with `%s`.',
      'ESLint',
      'npm install -g eslint');
  }

  public function getUpdateInstructions() {
    return pht(
      'Update %s with `%s`.',
      'ESLint',
      'npm update -g eslint');
  }

  protected function getMandatoryFlags() {
    $options = array();

    $options[] = '--cache=false';
    $options[] = '--format=json';

    if ($this->config) {
      $options[] = '--config='.$this->config;
    }

    if ($this->env) {
      $options[] = '--env='.$this->env;
    }

    return $options;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'eslint.config' => array(
        'type' => 'optional string',
        'help' => pht('%s configuration file.', 'ESLint'),
      ),
      'eslint.env' => array(
        'type' => 'optional string',
        'help' => pht('Enables specific environments.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'eslint.config':
        $this->config = $value;
        return;

      case 'eslint.env':
        $this->env = $value;
        return;

      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $files = array();

    try {
      $files = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('`%s` returned unparseable output.', 'eslint'),
        $ex);
    }

    $messages = array();

    foreach ($files as $file) {
      $lines = idx($file, 'messages', array());

      foreach ($lines as $line) {
        $message = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine(idx($line, 'line'))
          ->setChar(idx($line, 'column'))
          ->setDescription(idx($line, 'message'));

        switch (idx($line, 'severity')) {
          case 1:
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            break;

          case 2:
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;

          default:
            // This shouldn't be reached, but just in case...
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
            break;
        }

        if (idx($line, 'fatal', false)) {
          $message->setCode('fatal');
          $message->setName('ESLint Fatal');
        } else {
          $message->setCode(idx($line, 'ruleId'));
          $message->setName('ESLint '.idx($line, 'ruleId'));

          // TODO: Source contains the whole line, is this better than nothing?
          $message->setOriginalText(
            substr(idx($line, 'source'), $message->getChar() - 1));
        }

        $messages[] = $message;
      }

      return $messages;
    }
  }

}
