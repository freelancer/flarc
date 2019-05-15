<?php

final class ArcanistKTLintLinter extends ArcanistExternalLinter {

  public function getInfoName(): string {
    return 'ktlint';
  }

  public function getInfoURI(): string {
    return 'https://ktlint.github.io';
  }

  public function getInfoDescription(): string {
    return pht('An anti-bikeshedding Kotlin linter with built-in formatter.');
  }

  public function getLinterName(): string {
    return 'ktlint';
  }

  public function getLinterConfigurationName(): string {
    return 'ktlint';
  }

  protected function getDefaultMessageSeverity($code): string {
    // NOTE: `ktlint` doesn't seem to support severities,
    // see https://bit.ly/2HtRNQj.
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function getDefaultBinary(): string {
    return 'ktlint';
  }

  public function getInstallInstructions(): string {
    return pht(
      'See %s for installation instructions.',
      'https://ktlint.github.io/#getting-started');
  }

  protected function getMandatoryFlags(): array {
    return [
      '--reporter=json',
    ];
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = null;
    $regex = '/^(?P<version>\d+\.\d+\.\d+)$/';

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
    try {
      $this->checkBinaryConfiguration();
    } catch (ArcanistMissingLinterException $e) {
      return null;
    }

    return $this->getVersion();
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    try {
      $files = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          "Failed to parse `%s` output.\n\nSTDOUT\n%s",
          'ktlint',
          $stdout),
        $ex);
    }

    return array_map(
      function (array $error) use ($path): ArcanistLintMessage {
        return (new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($error['line'])
          ->setChar($error['column'])
          ->setCode($error['rule'])
          ->setSeverity($this->getLintMessageSeverity($error['rule']))
          ->setName($error['rule'])
          ->setDescription($error['message']);
      },
      array_mergev(array_column($files, 'errors')));
  }

}
