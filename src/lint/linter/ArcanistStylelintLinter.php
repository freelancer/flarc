<?php

final class ArcanistStylelintLinter extends ArcanistExternalLinter {

  private $config;

  public function getInfoName(): string {
    return 'stylelint';
  }

  public function getInfoURI(): string {
    return 'https://stylelint.io';
  }

  public function getInfoDescription(): string {
    return pht(
      'A mighty, modern linter that helps you avoid errors and '.
      'enforce conventions in your styles.');
  }

  public function getLinterName(): string {
    return 'stylelint';
  }

  public function getLinterConfigurationName(): string {
    return 'stylelint';
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'stylelint.config' => [
        'type' => 'optional string',
        'help' => pht('Path to a %s configuration file.', 'stylelint'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'stylelint.config':
        $this->config = $value;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  protected function getDefaultMessageSeverity($code): ?string {
    return null;
  }

  public function getDefaultBinary(): string {
    return 'stylelint';
  }

  public function getInstallInstructions(): string {
    return pht(
      'Install %s with `%s`.',
      'stylelint',
      'npm install --global stylelint');
  }

  public function getUpdateInstructions(): string {
    return pht(
      'Update %s with `%s`.',
      'stylelint',
      'npm install --global stylelint');
  }

  protected function getMandatoryFlags(): array {
    // TODO: Consider adding the following additional flags:
    //
    //   - `--config-basedir`
    //   - `--ignore-path`
    //   - `--syntax`
    //   - `--ignore-disables`
    //   - `--disable-default-ignores`
    //   - `--cache`
    //   - `--report-needless-disables`
    $flags = [
      '--formatter=json',
    ];

    if ($this->config !== null) {
      $flags[] = '--config='.$this->config;
    }

    return $flags;
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = null;
    $regex = '/^(?<version>\d+(?:\.\d+){2})$/';

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
      $files = phutil_json_decode($stderr);
    } catch (PhutilJSONParserException $ex) {
      try {
        $files = phutil_json_decode($stdout);
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

    }

    return array_map(
      function (array $warning) use ($path): ArcanistLintMessage {
        // TODO: We could possibly make this more human-readable.
        $name = $warning['rule'];

        // Remove rule suffix from description text.
        $description = rtrim($warning['text'], ' ('.$warning['rule'].')');

        $message = (new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($warning['line'])
          ->setChar($warning['column'])
          ->setCode($warning['rule'])
          ->setName($name)
          ->setDescription($description);

        // Map stylelint severities to `ArcanistLintSeverity`.
        $severity_map = [
          'error'   => ArcanistLintSeverity::SEVERITY_ERROR,
          'warning' => ArcanistLintSeverity::SEVERITY_WARNING,
        ];

        $message->setSeverity(
          coalesce(
            $this->getLintMessageSeverity($warning['rule']),
            $severity_map[$warning['severity']]));

        return $message;
      },
      array_mergev(array_column($files, 'warnings')));
  }

}
