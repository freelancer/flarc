<?php

final class ArcanistPHPMDLinter extends ArcanistExternalLinter {

  private $ruleset;

  public function getInfoName(): string {
    return 'PHPMD';
  }

  public function getInfoURI(): string {
    return 'https://github.com/phpmd/phpmd';
  }

  public function getInfoDescription(): string {
    return pht(
      'PHPMD is a spin-off project of PHP Depend and aims to be a PHP '.
      'equivalent of the well known Java tool PMD.');
  }

  public function getInstallInstructions(): string {
    return pht(
      'Install PHPMD with `%s`.',
      'composer global require phpmd/phpmd');
  }

  public function getLinterName(): string {
    return 'PHPMD';
  }

  public function getLinterConfigurationName(): string {
    return 'phpmd';
  }

  public function getDefaultBinary(): string {
    return 'phpmd';
  }

  protected function getMandatoryFlags(): array {
    return ['--suffixes', 'php,lint-test'];
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'phpmd.ruleset' => [
        'type' => 'string',
        'help' => pht('The name or path of the ruleset to use.'),
      ],
    ];
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'phpmd.ruleset':
        $this->ruleset = $value;
        break;

      default:
        parent::setLinterConfigurationValue($key, $value);
        break;
    }
  }

  public function getVersion(): ?string {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());
    $matches = [];

    $regex = '/^PHPMD (?P<version>\d+\.\d+\.\d+)\b/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return null;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr): array {
    if (strlen($stderr)) {
      throw new RuntimeException(
        pht(
          "`%s` returned an error:\n\n%s", 'phpmd',
          $stderr));
    }

    // Retrieve linter data from XML.
    $dom = new DOMDocument();
    $ok = @$dom->loadXML($stdout);
    if ($ok === false) {
      throw new RuntimeException(pht('Unable to load PHPMD XML.'));
    }

    $messages = [];
    $files = $dom->getElementsByTagName('file');
    foreach ($files as $file) {
      $violations = $file->getElementsByTagName('violation');
      foreach ($violations as $violation) {
        $messages[] = (new ArcanistLintMessage())
          ->setName($violation->getAttribute('ruleset'))
          ->setPath($path)
          ->setLine((int)$violation->getAttribute('beginline'))
          ->setChar(0)
          ->setDescription($violation->textContent)
          ->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
      }
    }

    return $messages;
  }

  protected function getPathArgumentForLinterFuture($path): PhutilCommandString {
    return csprintf('%Ls', [$path, 'xml', $this->ruleset]);
  }
}
