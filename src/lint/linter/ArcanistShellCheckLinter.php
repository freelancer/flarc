<?php

final class ArcanistShellCheckLinter extends ArcanistExternalLinter {

  private $shell;

  public function getInfoName(): string {
    return 'ShellCheck';
  }

  public function getInfoURI(): string {
    return 'https://www.shellcheck.net';
  }

  public function getInfoDescription(): string {
    return pht('A static analysis tool for shell scripts.');
  }

  public function getLinterName(): string {
    return 'SC';
  }

  public function getLinterConfigurationName(): string {
    return 'shellcheck';
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'shellcheck.shell' => [
        'type' => 'optional string',
        'help' => pht('Specify shell dialect (e.g. `%s` or `%s`).', 'bash', 'sh'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'shellcheck.shell':
        $this->shell = $value;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  public function getDefaultBinary(): string {
    return 'shellcheck';
  }

  public function getInstallInstructions(): string {
    return pht(
      'Install ShellCheck with `%s`.',
      'cabal install ShellCheck');
  }

  protected function getMandatoryFlags(): array {
    $flags = [
      '--format=json',
    ];

    if ($this->shell !== null) {
      $flags[] = '--shell='.$this->shell;
    }

    return $flags;
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = null;
    $regex = '/^version: (?P<version>\d+\.\d+\.\d+)$/';

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

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    try {
      $messages = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          "Failed to parse `%s` output.\n\nSTDOUT\n%s",
          'shellcheck',
          $stdout),
        $ex);
    }

    return array_map(
      function (array $message) use ($path): ArcanistLintMessage {
        switch ($message['level']) {
          case 'error':
            $severity = ArcanistLintSeverity::SEVERITY_ERROR;
            break;

          case 'warning':
            $severity = ArcanistLintSeverity::SEVERITY_WARNING;
            break;

          case 'info':
            $severity = ArcanistLintSeverity::SEVERITY_ADVICE;
            break;

          default:
            $severity = ArcanistLintSeverity::SEVERITY_ERROR;
            break;
        }

        return (new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($message['line'])
          ->setChar($message['column'])
          ->setCode($message['code'])
          ->setSeverity($severity)
          ->setName($this->getLintMessageFullCode($message['code']))
          ->setDescription($message['message']);
      },
      $messages);
  }

}
