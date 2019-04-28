<?php

final class ArcanistHadoLintLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'HadoLint';
  }

  public function getInfoURI() {
    return 'https://github.com/hadolint/hadolint';
  }

  public function getInfoDescription() {
    return pht(
      'A smarter Dockerfile linter that helps you build best practice Docker
      images.');
  }

  public function getLinterName() {
    return 'HadoLint';
  }

  public function getLinterConfigurationName() {
    return 'hadolint';
  }

  public function getDefaultBinary() {
    return 'hadolint';
  }

  public function shouldExpectCommandErrors(): bool {
    return true;
  }

  public function getVersion() {
    list($stdout) = execx(
      '%C --version',
      $this->getExecutableCommand());

    $matches = [];
    if (!preg_match('/^v(?P<version>\d+\.\d+\.\d+)-/', $stdout, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  public function getInstallInstructions() {
    return pht(
      'Install %s with `%s`.',
      'hadolint',
      'docker pull hadolint/hadolint');
  }

  public function getUpdateInstructions() {
    return pht(
      'Update %s with `%s`.',
      'hadolint',
      'docker pull hadolint/hadolint');
  }

  /**
   * Parse the output from hadolint.
   *
   * Here is an example of the output:
   *
   * ```
   * support/build/BaseImage.Dockerfile:4 SC2046 Quote this to prevent word
   * splitting.
   * ```
   */
  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $errors = [];

    if (strlen($stderr)) {
      throw new RuntimeException(
        pht("`%s` returned an error:\n\n%s", 'hadolint', $stderr));
    }

    $errors = explode("\n", $stdout);
    $messages = [];

    foreach ($errors as $error) {
      if ($error == '') {
        continue;
      }
      // Remove the file name.
      $error = ltrim(strstr($error, ':'), ':');
      $parts = explode(' ', $error);

      $message = id(new ArcanistLintMessage())
        ->setName($this->getLinterName())
        ->setPath($path)
        ->setLine((int)$parts[0])
        ->setChar(0)
        ->setCode($parts[1])
        ->setDescription(implode(' ', array_slice($parts, 2)))
        ->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);

      $messages[] = $message;
    }
    return $messages;
  }
}
