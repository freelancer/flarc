<?php

/**
 * @todo This linter should no longer be required after
 *       https://secure.phabricator.com/D17854.
 */
final class FlarcPuppetLintLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'puppet-lint';
  }

  public function getInfoURI() {
    return 'http://puppet-lint.com/';
  }

  public function getInfoDescription() {
    return pht(
      'Use `%s` to check that your Puppet manifests '.
      'conform to the style guide.',
      'puppet-lint');
  }

  public function getLinterName() {
    return 'PUPPETLINT';
  }

  public function getLinterConfigurationName() {
    // Ideally this would be set to `puppet-lint`, but doing so would conflict
    // with `ArcanistPuppetLintLinter`.
    return 'flarc-puppet-lint';
  }

  public function getDefaultBinary() {
    return 'puppet-lint';
  }

  public function getInstallInstructions() {
    return pht(
      'Install puppet-lint using `%s`.',
      'gem install puppet-lint');
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^puppet-lint (?P<version>\d+\.\d+\.\d+)$/';

    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  protected function getMandatoryFlags() {
    return [
      '--error-level=all',
      '--json',
    ];
  }

  protected function parseLinterOutput($path, $status, $stdout, $stderr) {
    $output = idx(phutil_json_decode($stdout), 0);
    $messages = [];

    foreach ($output as $message) {
      $messages[] = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine($message['line'])
        ->setChar($message['column'])
        ->setCode(strtoupper($message['check']))
        ->setSeverity($this->getLintMessageSeverity($message['kind']))
        ->setName(ucwords(str_replace('_', ' ', $message['check'])))
        ->setDescription(ucfirst($message['message']));
    }

    return $messages;
  }

  protected function getDefaultMessageSeverity($kind) {
    switch ($kind) {
      case 'error':
        return ArcanistLintSeverity::SEVERITY_ERROR;

      case 'warning':
        return ArcanistLintSeverity::SEVERITY_WARNING;

      default:
        return ArcanistLintSeverity::SEVERITY_ADVICE;
    }
  }

}
