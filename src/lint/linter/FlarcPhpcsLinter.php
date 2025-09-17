<?php

/**
 * This class extends ArcanistExternalLinter and acts as a wrapper around
 * the ArcanistPhpcsLinter. It delegates all method calls and behavior
 * to the underlying ArcanistPhpcsLinter instance.
 */
final class FlarcPhpcsLinter extends ArcanistBatchExternalLinter {

  private $delegate;

  public function __construct() {
    $this->delegate = new ArcanistPhpcsLinter();
  }

  public function getInfoName(): string {
    return $this->delegate->getInfoName();
  }

  public function getInfoURI(): string {
    return $this->delegate->getInfoURI();
  }

  public function getInfoDescription(): string {
    return $this->delegate->getInfoDescription();
  }

  public function getLinterName(): string {
    return $this->delegate->getLinterName();
  }

  public function setCustomSeverityMap(array $map) {
    $this->delegate->setCustomSeverityMap($map);
    return $this;
  }

  public function addCustomSeverityMap(array $map) {
    $this->delegate->addCustomSeverityMap($map);
    return $this;
  }

  public function setCustomSeverityRules(array $rules) {
    $this->delegate->setCustomSeverityRules($rules);
    return $this;
  }

  public function getLintSeverityMap(): array {
    return $this->delegate->getLintSeverityMap();
  }

  public function getLintNameMap(): array {
    return $this->delegate->getLintNameMap();
  }

  protected function canCustomizeLintSeverities(): bool {
    return true;
  }



  public function getLinterConfigurationName(): string {
    return 'flarc-'.$this->delegate->getLinterConfigurationName();
  }

  public function getInstallInstructions(): string {
    return $this->delegate->getInstallInstructions();
  }

  public function getLinterConfigurationOptions(): array {
    // Merge wrapper's standard options (bin, flags, version, interpreter)
    // with delegate-specific options (e.g. phpcs.standard).
    return $this->delegate->getLinterConfigurationOptions()
      + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      // These must be applied to THIS wrapper so interpreter/bin/flags are
      // honoured by the runtime executable composed by the wrapper.
      case 'interpreter':
      case 'bin':
      case 'flags':
      case 'version':
        parent::setLinterConfigurationValue($key, $value);
        return;

      default:
        if ($key !== 'phpcs.standard') {
          parent::setLinterConfigurationValue($key, $value);
        }
        $this->delegate->setLinterConfigurationValue($key, $value);
        return;
    }
  }

  public function getDefaultBinary(): string {
    return $this->delegate->getDefaultBinary();
  }

  public function shouldUseInterpreter(): bool {
    return true;
  }

  public function getDefaultInterpreter(): ?string {
    return null;
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^PHP_CodeSniffer version (?<version>\d+(?:\.\d+){2})\b/';
    if (!preg_match($regex, $stdout, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  protected function getMandatoryFlags(): array {
    return $this->delegate->getMandatoryFlags();
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    // PHPCS may emit leading newlines or notices before the XML report.
    // Trim any noise before the XML declaration or root <phpcs> node.
    $pos = null;
    $m = [];
    if (preg_match('/(<\?xml|<phpcs)/', $stdout, $m, PREG_OFFSET_CAPTURE)) {
      $pos = $m[0][1];
    }
    if ($pos !== null && $pos > 0) {
      $stdout = substr($stdout, $pos);
    }

    // For batch runs, we must extract the file path from the report.
    // If execution succeeded with no messages, return an empty array.
    if (!$err && !strlen(trim($stdout))) {
      return [];
    }

    $dom = new DOMDocument();
    $ok = @$dom->loadXML($stdout);
    if ($ok === false) {
      return false;
    }

    $project_root = $this->getProjectRoot();
    $files = $dom->getElementsByTagName('file');
    $messages = [];

    foreach ($files as $file) {
      $file_name = $file->getAttribute('name');
      $relative_path = Filesystem::readablePath($file_name, $project_root);

      foreach ($file->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
          continue;
        }

        $prefix = ($child->tagName === 'error') ? 'E' : 'W';
        $source = $child->getAttribute('source');
        $code   = 'PHPCS.'.$prefix.'.'.$source;

        $messages[] = (new ArcanistLintMessage())
          ->setPath($relative_path)
          ->setName($source)
          ->setLine((int)$child->getAttribute('line'))
          ->setChar((int)$child->getAttribute('column'))
          ->setCode($code)
          ->setDescription($child->textContent)
          ->setSeverity($this->delegate->getLintMessageSeverity($code));
      }
    }

    return $messages;
  }

  protected function getDefaultMessageSeverity($code) {
    return $this->delegate->getDefaultMessageSeverity($code);
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    return $this->delegate->getLintCodeFromLinterConfigurationKey($code);
  }

  public function __clone() {
    if ($this->delegate !== null) {
      // This is the same as we did in ArcanistDockerContainerLinterProxy
      $this->delegate = clone $this->delegate;
    }
  }
}
