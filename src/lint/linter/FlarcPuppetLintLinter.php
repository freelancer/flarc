<?php

/**
 * An improved version of @{class:ArcanistPuppetLintLinter}.
 *
 * An improved version of @{class:ArcanistPuppetLintLinter} which uses `--json`
 * instead of `--log-format`.
 *
 * @todo This linter should no longer be required after
 *   https://secure.phabricator.com/D17854.
 */
final class FlarcPuppetLintLinter extends ArcanistExternalLinter {

  public function getInfoName(): string {
    return 'Puppet Lint';
  }

  public function getInfoURI(): string {
    return 'http://puppet-lint.com';
  }

  public function getInfoDescription(): string {
    return pht('Check that your Puppet manifests conform to the style guide.');
  }

  public function getLinterName(): string {
    return 'Puppet Lint';
  }

  public function getLinterConfigurationName(): string {
    // NOTE: Ideally this would be set to `puppet-lint`, but doing so would
    // conflict with `ArcanistPuppetLintLinter`.
    return 'flarc-puppet-lint';
  }

  public function getDefaultBinary(): string {
    return 'puppet-lint';
  }

  public function getInstallInstructions(): string {
    return pht(
      'Install `%s` using `%s`.',
      'puppet-lint',
      'gem install puppet-lint');
  }

  public function getUpdateInstructions(): string {
    return pht(
      'Update `%s` using `%s`.',
      'puppet-lint',
      'gem install puppet-lint');
  }

  protected function getMandatoryFlags(): array {
    return [
      '--error-level=all',
      '--json',
    ];
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = null;
    $regex = '/^puppet-lint (?P<version>\d+\.\d+\.\d+)$/';

    if (!preg_match($regex, $stdout, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  /**
   * Get a version string used for caching lint results.
   *
   * The implementation of this method was mostly copied from
   * @{method:ArcanistExternalLinter::getCacheVersion} but was adapted in order
   * to ensure that only flags which actually affect the linter results are
   * used within the lint cache key.
   */
  public function getCacheVersion(): ?string {
    // TODO: Implement this method.
    return parent::getCacheVersion();
  }

  protected function parseLinterOutput($path, $status, $stdout, $stderr): array {
    $results = phutil_json_decode($stdout)[0];

    return array_map(
      function (array $result) use ($path): ArcanistLintMessage {
        $name = ucwords(str_replace('_', ' ', $result['check']));

        $message = (new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($result['line'])
          ->setChar($result['column'])
          ->setCode($result['check'])
          ->setName($name)
          ->setDescription(ucfirst($result['message']));

        switch ($result['kind']) {
          case 'error':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;

          case 'warning':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            break;

          default:
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
            break;
        }

        return $message;
      },
      $results);
  }

}
