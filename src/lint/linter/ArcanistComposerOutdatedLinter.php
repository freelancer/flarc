<?php

/**
 * TODO: Can we merge this into `ArcanistComposerLinter`?
 */
final class ArcanistComposerOutdatedLinter extends ArcanistExternalLinter {

  public function getInfoName(): string {
    return pht('Composer Outdated');
  }

  public function getInfoUrl(): string {
    return 'https://getcomposer.org';
  }

  public function getInfoDescription(): string {
    return pht('Checks whether Composer packages are up-to-date.');
  }

  public function getLinterName(): string {
    return 'Composer Outdated';
  }

  public function getLinterConfigurationName(): string {
    return 'composer-outdated';
  }

  public function getDefaultBinary(): string {
    return 'composer';
  }

  public function getInstallInstructions(): string {
    return pht(
      'See %s for installation instructions.',
      'https://getcomposer.org');
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = null;
    $regex = '/^Composer version (?<version>\d+(?:\.\d+){2})\b/';

    if (!preg_match($regex, $stdout, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  protected function getMandatoryFlags(): array {
    return [
      'outdated',
      '--direct',
      '--format=json',
    ];
  }

  public function shouldExpectCommandErrors(): bool {
    return false;
  }

  /**
   * composer outdated command should not take in any path argument
   *
   * @throws PhutilProxyException
   */
  protected function buildFutures(array $paths) {
    $bin = csprintf('%C %Ls', $this->getExecutableCommand(), $this->getCommandFlags());
    $futures = [];

    foreach ($paths as $path) {
      $disk_path = $this->getEngine()->getFilePathOnDisk($path);

      $futures[$path] = (new ExecFuture('%C', $bin))
        ->setEnv([
          'COMPOSER' => $disk_path,
          'HOME'     => getenv('HOME'),
          'PATH'     => getenv('PATH'),
        ])
        ->setCWD($this->getProjectRoot());
    }

    return $futures;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    try {
      $packages = phutil_json_decode($stdout);
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

    return array_map(
      function (array $package) use ($path): ArcanistLintMessage {
        return (new ArcanistLintMessage())
          ->setPath($path)
          ->setCode($package['latest-status'])
          ->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE)
          ->setName(pht('Composer Package Outdated'))
          ->setDescription($this->formatMessage($package))
          ->setBypassChangedLineFiltering(true);
      },
      $packages['installed']);

    $messages = [];
    foreach ($packages['installed'] as $package) {
      $messages[] = (new ArcanistLintMessage())
        ->setCode(pht('Composer Package Outdated'))
        ->setName(pht('%s', $package['latest-status']))
        ->setDescription(pht('%s', $this->formatMessage($package)))
        ->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE)
        ->setBypassChangedLineFiltering(true);
    }

    return $messages;
  }

  private function formatMessage(array $package): string {
    if (isset($package['warning'])) {
      return $package['warning'];
    }

    $formatted_str = pht(
      'Package `%s` is outdated, consider updating it from `%s` to `%s`.',
      $package['name'],
      $package['version'],
      $package['latest']);

    switch ($package['latest-status']) {
      case 'semver-safe-update':
        $formatted_str .= ' '.pht('Update is minor and safe.');
        break;

      case 'update-possible':
        $formatted_str .= ' '.pht('Caution: Update might be backward incompatible.');
        break;
    }

    return $formatted_str;
  }

}
