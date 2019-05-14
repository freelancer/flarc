<?php

/**
 * A proxy linter that can be used to execute an @{class:ArcanistExternalLinter}
 * within a Docker container.
 *
 * For example, suppose that you want to use @{class:ArcanistRuboCopLinter} but,
 * for whatever reason, you don't want developers to need to have RuboCop
 * installed locally (or perhaps even Ruby itself).
 *
 * ```lang=json, name=.arclint
 * {
 *   "linters": {
 *     "rubocop": {
 *       "type": "docker-proxy",
 *       "include": "(\\.rb$)",
 *
 *       "docker-proxy.image.name": "kakakakakku/rubocop",
 *       "docker-proxy.linter.type": "rubocop",
 *       "docker-proxy.linter.config": {
 *         "rubocop.config": ".rubocop.yml"
 *       },
 *       "severity": {
 *         "Style/StringLiterals": "error"
 *       }
 *     }
 *   }
 * }
 * ```
 */
final class ArcanistDockerContainerLinterProxy extends ArcanistExternalLinter {

  private $image;
  private $proxiedLinter;

  /**
   * @todo We should maybe attempt to validate the image name (see
   *   https://stackoverflow.com/a/37867949).
   */
  public function getImage(): string {
    if ($this->image === null) {
      throw new PhutilInvalidStateException('setImage');
    }

    return $this->image;
  }

  public function getProxiedLinter(): ArcanistExternalLinter {
    if ($this->proxiedLinter === null) {
      throw new PhutilInvalidStateException('setProxiedLinter');
    }

    return $this->proxiedLinter;
  }

  public function setImage(string $image) {
    $this->image = $image;
    return $this;
  }

  public function setProxiedLinter(ArcanistExternalLinter $linter) {
    $engine = $this->getEngine();

    if ($this->engine === null) {
      throw new PhutilInvalidStateException('setEngine');
    }

    $linter->setEngine($engine);
    $this->proxiedLinter = $linter;

    return $this;
  }


/* -(  ArcanistLinter  )----------------------------------------------------- */


  public function getInfoDescription(): string {
    return pht(
      'Proxies calls to an `%s` by executing external commands '.
      'within a Docker container.',
      parent::class);
  }

  public function getInfoName(): string {
    return pht('Docker Container Linter Proxy');
  }

  public function getLinterPriority(): float {
    return $this->getProxiedLinter()->getLinterPriority();
  }

  public function setCustomSeverityMap(array $map) {
    $this->getProxiedLinter()->setCustomSeverityMap($map);
    return $this;
  }

  public function addCustomSeverityMap(array $map) {
    $this->getProxiedLinter()->addCustomSeverityMap($map);
    return $this;
  }

  public function setCustomSeverityRules(array $rules) {
    $this->getProxiedLinter()->setCustomSeverityRules($rules);
    return $this;
  }

  public function getCacheVersion(): ?string {
    // TODO: This method isn't currently proxied because calling
    // `$this->getProxiedLinter()->getCacheVersion()` will bypass Docker.
    return parent::getCacheVersion();
  }

  public function canRun(): bool {
    return $this->getProxiedLinter()->canRun();
  }

  public function getLinterName(): string {
    return $this->getProxiedLinter()->getLinterName();
  }

  public function getVersion(): ?string {
    // TODO: This method isn't currently proxied because calling
    // `$this->getProxiedLinter()->getVersion()` will bypass Docker.
    return parent::getVersion();
  }

  public function getLintSeverityMap(): array {
    return $this->getProxiedLinter()->getLintSeverityMap();
  }

  public function getLintNameMap(): array {
    return $this->getProxiedLinter()->getLintNameMap();
  }

  public function getCacheGranularity(): int {
    return $this->getProxiedLinter()->getCacheGranularity();
  }

  public function getLinterConfigurationName(): ?string {
    return 'docker-proxy';
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'docker-proxy.image.name' => [
        'type' => 'string',
        'help' => pht(
          'Docker container image in which to execute the external '.
          'linter commands.'),
      ],
      'docker-proxy.linter.type' => [
        'type' => 'string',
        'help' => pht('`%s` to be proxied.', parent::class),
      ],
      'docker-proxy.linter.config' => [
        'type' => 'optional map<string,wild>',
        'help' => pht('Configuration for the proxied `%s`.', parent::class),
      ],
    ];

    return $options + ArcanistLinter::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'docker-proxy.image.name':
        $this->setImage($value);
        break;

      case 'docker-proxy.linter.type':
        $linters = (new PhutilClassMapQuery())
          ->setAncestorClass(parent::class)
          ->setUniqueMethod('getLinterConfigurationName', true)
          ->execute();

        if (empty($linters[$value])) {
          throw new ArcanistUsageException(
            pht(
              "Linter specifies invalid type '%s'. ".
              "Available linters are: %s.",
              $value,
              implode(', ', array_keys($linters))));
        }

        $this->setProxiedLinter(clone $linters[$value]);
        break;

      case 'docker-proxy.linter.config':
        foreach ($value as $k => $v) {
          $this->getProxiedLinter()->setLinterConfigurationValue($k, $v);
        }
        break;

      default:
        parent::setLinterConfigurationValue($key, $value);
        break;
    }
  }

  protected function canCustomizeLintSeverities(): bool {
    // NOTE: This condition should only be `true` in `ArcanistLintersWorkflow`,
    // in which case we just assume that lint severities can be customized.
    if ($this->proxiedLinter === null) {
      return true;
    }

    return $this->getProxiedLinter()->canCustomizeLintSeverities();
  }

  protected function shouldLintBinaryFiles(): bool {
    return $this->getProxiedLinter()->shouldLintBinaryFiles();
  }

  protected function shouldLintDeletedFiles(): bool {
    return $this->getProxiedLinter()->shouldLintDeletedFiles();
  }

  protected function shouldLintDirectories(): bool {
    return $this->getProxiedLinter()->shouldLintDirectories();
  }

  protected function shouldLintSymbolicLinks(): bool {
    return $this->getProxiedLinter()->shouldLintSymbolicLinks();
  }

  protected function getLintCodeFromLinterConfigurationKey($code): string {
    return $this->getProxiedLinter()->getLintCodeFromLinterConfigurationKey($code);
  }


/* -(  ArcanistFutureLinter  )----------------------------------------------- */

  protected function didResolveLinterFutures(array $futures): void {
    $this->getProxiedLinter()->didResolveLinterFutures($futures);
  }


/* -(  ArcanistExternalLinter  )--------------------------------------------- */


  public function getDefaultBinary(): string {
    return 'docker';
  }

  public function getInstallInstructions(): string {
    return pht(
      'See %s for installation instructions.',
      'https://docs.docker.com/install/');
  }

  public function shouldExpectCommandErrors(): bool {
    return $this->getProxiedLinter()->shouldExpectCommandErrors();
  }

  protected function getMandatoryFlags(): array {
    $project_root = $this->getProjectRoot();

    $flags = [
      'run',
      '--mount',
      sprintf('type=bind,source=%s,target=%s', $project_root, $project_root),
      '--rm',
      sprintf('--workdir=%s', $project_root),
    ];

    return $flags;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    return $this->getProxiedLinter()->parseLinterOutput($path, $err, $stdout, $stderr);
  }

  protected function getPathArgumentForLinterFuture($path): PhutilCommandString {
    return $this->getProxiedLinter()->getPathArgumentForLinterFuture($path);
  }

  protected function buildFutures(array $paths): array {
    $bin = csprintf(
      '%C %Ls %s %C %Ls',
      $this->getExecutableCommand(),
      $this->getCommandFlags(),
      $this->getImage(),
      $this->getProxiedLinter()->getExecutableCommand(),
      $this->getProxiedLinter()->getCommandFlags());
    $futures = [];

    foreach ($paths as $path) {
      $disk_path     = $this->getEngine()->getFilePathOnDisk($path);
      $path_argument = $this->getPathArgumentForLinterFuture($disk_path);

      $future = new ExecFuture('%C %C', $bin, $path_argument);
      $future->setCWD($this->getProjectRoot());

      $futures[$path] = $future;
    }

    return $futures;
  }

}
