<?php

final class ArcanistMypyLinter extends ArcanistExternalLinter {

  public function getInfoName(): string {
    return 'Python Mypy';
  }

  public function getInfoURI(): string {
    return 'https://github.com/python/mypy';
  }

  public function getInfoDescription(): string {
    return pht('Mypy is a static type checker for Python.');
  }

  public function getLinterName(): string {
    return 'MYPY';
  }

  public function getLinterConfigurationName(): string {
    return 'mypy';
  }

  public function getDefaultBinary(): string {
    return 'mypy';
  }

  public function getInstallInstructions(): string {
    return pht(
      'Install Mypy using `%s`.',
      'pip3 install mypy');
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^mypy (?<version>\d+\.\d+(?:b\d+)?)$/';

    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function shouldExpectCommandErrors(): bool {
    return true;
  }

  protected function getMandatoryFlags() {
    $options = array();
    $options[] = '--show-column-numbers';
    return $options;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    if (!strlen($stdout)) {
      return [];
    }

    $lines = phutil_split_lines($stdout, false);
    $messages = array();

    foreach ($lines as $line) {
      // Mypy output format is not configurable, so we manually parse them.
      // Example outputs:
      // file.py:103:25: error: Error message  [error-code]
      // file.py:103:25: note: Warning messages
      $file = $lineno = $charno = $severity = $detail = null;
      $num = sscanf($line, "%[^:]:%d:%d: %[^:]: %[^\t\n]",
        $file, $lineno, $charno, $severity, $detail);

      if ($num < 5) {
        continue;
      }
      $details = explode('  ', $detail);
      $code = 'advice';
      if (count($details) == 2) {
        $code = str_replace(array('[', ']') , ''  , $details[1]);
      }
      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine($lineno)
        ->setChar($charno)
        ->setCode($code)
        ->setName('mypy')
        ->setSeverity($this->getLintMessageSeverity($severity))
        ->setDescription($details[0]);
      $messages[] = $message;
    }
    return $messages;
  }

  protected function getDefaultMessageSeverity($severity) {
    switch ($severity) {
      case 'note':
        return ArcanistLintSeverity::SEVERITY_WARNING;
      case 'error':
        return ArcanistLintSeverity::SEVERITY_ERROR;
      default:
        return ArcanistLintSeverity::SEVERITY_ADVICE;
    }
  }
}
