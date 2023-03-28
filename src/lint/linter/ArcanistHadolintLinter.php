<?php

final class ArcanistHadolintLinter extends ArcanistExternalLinter {

  public function getInfoName(): string {
    return 'Hadolint';
  }

  public function getInfoURI(): string {
    return 'https://github.com/hadolint/hadolint';
  }

  public function getInfoDescription(): string {
    return pht(
      'A smarter `%s` linter that helps you build best practice '.
      'Docker images.',
      'Dockerfile');
  }

  public function getLinterName(): string {
    return 'Hadolint';
  }

  public function getLinterConfigurationName(): string {
    return 'hadolint';
  }

  protected function getDefaultMessageSeverity($code): ?string {
    return null;
  }

  public function getDefaultBinary(): string {
    return 'hadolint';
  }

  public function getInstallInstructions(): string {
    return pht(
      'See %s for installation instructions.',
      'https://github.com/hadolint/hadolint#install');
  }

  protected function getMandatoryFlags(): array {
    return [
      '--format=json',
    ];
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = null;
    $regex = '/^Haskell Dockerfile Linter v(?<version>\d+(?:\.\d+){2})-/';

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
    // TODO: Do we need to `try`-`catch` this?
    try {
      $this->checkBinaryConfiguration();
    } catch (ArcanistMissingLinterException $e) {
      return null;
    }

    return $this->getVersion();
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    try {
      $messages = phutil_json_decode($stdout);
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
      function (array $message) use ($path): ArcanistLintMessage {
        switch ($message['level']) {
          case 'error':
            $severity = ArcanistLintSeverity::SEVERITY_ERROR;
            break;

          case 'info':
            $severity = ArcanistLintSeverity::SEVERITY_ADVICE;
            break;

          case 'warning':
            $severity = ArcanistLintSeverity::SEVERITY_WARNING;
            break;
        }

        return (new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($message['line'])
          ->setChar($message['column'])
          ->setCode($message['code'])
          ->setSeverity($severity)
          ->setName($this->getLinterName().' '.$message['code'])
          ->setDescription($message['message']);
      },
      $messages);
  }

}
