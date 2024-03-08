<?php

final class ArcanistRectorLinter extends ArcanistBatchExternalLinter {
  private $config;

  public function getInfoName(): string {
    return pht('rector');
  }

  public function getInfoUrl(): string {
    return 'https://getrector.com/documentation';
  }

  public function getInfoDescription(): string {
    return pht(
      'Rector is a PHP tool that you can run on any PHP project to get an instant'.
      ' upgrade or automated refactoring. It helps with PHP upgrades, framework'.
      ' upgrades and improves your code quality.');
  }

  public function getLinterName(): string {
    return 'rector';
  }

  public function getLinterConfigurationName(): string {
    return 'rector';
  }

  public function getDefaultBinary(): string {
    return 'rector';
  }

  public function getInstallInstructions(): string {
    return pht(
      'See %s.',
      'https://getrector.com/documentation');
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'rector.config' => [
        'type' => 'optional string',
        'help' => pht('The path to a %s configuration file', 'rector'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'rector.config':
        $this->config = $value;
        break;

      default:
        try {
          parent::setLinterConfigurationValue($key, $value);
        } catch (Exception $e) {
          $message = <<<DOC
{$e->getMessage()}

FIX: Try 'composer install'

DOC;

          throw new Exception(
            $message,
            $e->getCode(),
            $e
          );
        }
        break;
    }
  }

  public function shouldExpectCommandErrors(): bool {
    return true;
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^Rector (?<version>\d+(?:\.\d+){2})\b/';

    if (!preg_match($regex, $stdout, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  protected function shouldLintDirectories(): bool {
    return true;
  }

  protected function getMandatoryFlags(): array {
    $flags = [
      'process',
      '--dry-run',
      '--output-format=json',
      '-vvv',
    ];

    // Config always last to override any other config
    if ($this->config !== null) {
      $flags[] = '--config='.$this->config;
    }

    // To pass individual path rector expects -- following the flags
    $flags[] = '--';

    return $flags;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    // Error code 0 = No changes
    // Error code 2 = Changes detected
    // Error code 1 = Error
    if ($err !== 0 && $err !== 2) {
      throw new CommandException(
        pht(
          'Failed to run `%s` on %s. Exit code: %d',
          $this->getLinterName(),
          $path,
          $err),
        $this->getLinterName(),
        $err,
        $stdout,
        $stderr
      );
    }

    try {
      $report = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          "Failed to parse `%s` output. Expecting valid JSON.\n\n".
          "Exception:\n%s\n\nSTDOUT\n%s\n\nSTDERR\n%s",
          $this->getLinterConfigurationName(),
          $ex->getMessage(),
          $stdout,
          $stderr),
        $ex
      );
    }

    $messages = [];
    foreach ($report['file_diffs'] ?? [] as $file) {
      $hunks = (new FlarcDiffParser())->parseDiff($file['diff']);

      foreach ($hunks as $hunk) {
        $messages[] = (new ArcanistLintMessage())
          ->setPath($file['file'])
          ->setLine($hunk->getLine())
          ->setChar(1) // first char in the line
          ->setCode('Rector')
          ->setName('Rector')
          ->setDescription(
            pht(
              '(%s) Please consider the following changes',
              implode(', ', $file['applied_rectors'])))
          ->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE)
          ->setBypassChangedLineFiltering(true)
          ->setOriginalText($hunk->getOriginal())
          ->setReplacementText($hunk->getReplacement());
      }
    }

    return $messages;
  }

}
