<?php

final class ArcanistGroovyLinter extends ArcanistExternalLinter {
  /** @var string */
  private $lintOutput;
  /** @var string */
  private $lintLogLevel;


  public function getInfoName(): string {
    return 'npm-groovy-lint';
  }

  public function getInfoUrl(): string {
    return 'https://github.com/nvuillam/npm-groovy-lint/blob/master/README.md';
  }

  public function getInfoDescription(): string {
    return pht('A linter for Jenkinsfiles/groovy files.');
  }

  public function getLinterName(): string {
    return 'npm-groovy-lint';
  }

  public function getLinterConfigurationName(): string {
    return 'npm-groovy-lint';
  }

  public function getDefaultBinary(): string {
    return 'node_modules/.bin/npm-groovy-lint';
  }

  public function getInstallInstructions(): string {
    return pht(
      'See %s for installation instructions.',
      'https://github.com/nvuillam/npm-groovy-lint/blob/master/README.md');
  }

  protected function getDefaultMessageSeverity($code): string {
    switch ($code) {
      case 'info':
        return ArcanistLintSeverity::SEVERITY_ADVICE;
      case 'warning':
        return ArcanistLintSeverity::SEVERITY_WARNING;
      default:
        return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  public function getVersion(): ?string {
    return null;
  }

  protected function getMandatoryFlags(): array {
    $options = [];

    if ($this->lintOutput) {
      $options[] = "--output {$this->lintOutput}";
    }

    if ($this->lintLogLevel) {
      $options[] = "--loglevel {$this->lintLogLevel}";
    }

    $options[] = '--no-insight';
    $options[] = '--noserver';

    return $options;
  }

  public function shouldExpectCommandErrors(): bool {
    return false;
  }

  protected function buildFutures(array $paths) {
    $executable = $this->getExecutableCommand();

    // HAX: join the flags without adding apostrophes since arc encloses parameters
    //  which leads to the binary file or command not recognizing the options.
    //
    // Example command which fails:
    //  `node_modules/.bin/npm-groovy-lint \
    //    '--loglevel warning' '--output json' \
    //    '--files "**/*.groovy,**/*.Jenkinsfile,**/Jenkinsfile.*"' \
    //     /home/pauljoshuarobles/gaf/support/build/merge-queue/Worker.Jenkinsfile`
    //
    // The command doesn't recognize the options and I get the error:
    // `Parse options error: Value for 'loglevel' of type 'String' required.`
    $flags = implode(' ', $this->getCommandFlags());
    $bin = csprintf('%C %C --files', $executable, $flags);

    $futures = array();
    foreach ($paths as $path) {
      $disk_path = $this->getEngine()->getFilePathOnDisk($path);
      $path_argument = $this->getPathArgumentForLinterFuture($disk_path);
      $future = new ExecFuture('%C %C', $bin, $path_argument);
      $future->setCWD($this->getProjectRoot());
      $futures[$path] = $future;
    }

    return $futures;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'groovy-lint.output' => array(
        'type' => 'optional string',
        'help' => pht('Output format for lint. Should be JSON so we can parse properly.'),
      ),
      'groovy-lint.loglevel' => array(
        'type' => 'optional string',
        'help' => pht('Log level. Temporarily set to error because we have a lot of issues.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'groovy-lint.output':
        $this->lintOutput = $value;
        return;
      case 'groovy-lint.loglevel':
        $this->lintLogLevel = $value;
        return;
      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    try {
      $output = phutil_json_decode($stdout);
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


    if (!$output) {
      return [];
    }

    $messages = [];
    foreach ($output['files'] as $file => $lint) {
      foreach ($lint['errors'] as $error) {
        $severity = $this->getLintMessageSeverity($error['severity']);
        $character = array_key_exists('range', $error)
          ? $error['range']['start']['character']
          : null;

        $messages[] = (new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($error['line'])
          ->setChar($character)
          ->setCode(idx($error, 'code', $this->getLinterName()))
          ->setName($error['rule'])
          ->setDescription($error['msg'])
          ->setSeverity($severity)
          ->setBypassChangedLineFiltering(true);
      }
    }

    return $messages;
  }
}
