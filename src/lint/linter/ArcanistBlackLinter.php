<?php

final class ArcanistBlackLinter extends ArcanistExternalLinter {

  public function getInfoName(): string {
    return 'Black';
  }

  public function getInfoURI(): string {
    return 'https://github.com/ambv/black';
  }

  public function getInfoDescription(): string {
    return pht('Black is the uncompromising Python code formatter.');
  }

  public function getLinterName(): string {
    return 'BLACK';
  }

  public function getLinterConfigurationName(): string {
    return 'black';
  }

  public function getDefaultBinary(): string {
    return 'black';
  }

  public function getInstallInstructions(): string {
    return pht(
      'Install Black using `%s`.',
      'pip3 install black');
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^black, version (?<version>\d+\.\d+(?:b\d+)?)$/';

    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function shouldExpectCommandErrors(): bool {
    return false;
  }

  protected function getMandatoryFlags(): array {
    return [
      '--diff',
    ];
  }

  protected function parseLinterOutput($path, $status, $stdout, $stderr): array {
    if (!strlen($stdout)) {
      return [];
    }

    $parser   = new ArcanistDiffParser();
    $messages = [];

    foreach ($parser->parseDiff($stdout) as $change) {
      foreach ($change->getHunks() as $hunk) {
        $original_text    = [];
        $replacement_text = [];

        // TODO: Can we use `ArcanistDiffParser` or `FlarcDiffParser` here?
        foreach (phutil_split_lines($hunk->getCorpus(), false) as $line) {
          $char = strlen($line) ? $line[0] : '~';
          $line = substr($line, 1);

          switch ($char) {
            case '-':
              $original_text[] .= $line;
              break;

            case '+':
              $replacement_text[] .= $line;
              break;

            case ' ':
              $original_text[] .= $line;
              $replacement_text[] .= $line;
              break;

            case '~':
              break;
          }
        }

        $original_text    = implode("\n", $original_text);
        $replacement_text = implode("\n", $replacement_text);

        $messages[] = (new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($hunk->getOldOffset())
          ->setChar(1)
          ->setCode($this->getLinterName())
          ->setSeverity(ArcanistLintSeverity::SEVERITY_AUTOFIX)
          ->setName('format')
          ->setOriginalText($original_text)
          ->setReplacementText($replacement_text)
          ->setBypassChangedLineFiltering(true);
      }
    }

    return $messages;
  }

}
