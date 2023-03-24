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

  const ENV_SHOULD_PROXY = 'DOCKER_LINTER_PROXY';

  private $image;
  private $mounts = [];
  private $proxiedLinter;
  private $shouldProxy;


  public function __clone() {
    if ($this->proxiedLinter !== null) {
      $this->proxiedLinter = clone $this->proxiedLinter;
    }
  }

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

  public function getMounts(): array {
    $mounts = $this->mounts;

    // Always mount the project root directory.
    array_unshift($mounts, $this->getProjectRoot());

    return array_map(
      function (string $path): string {
        return sprintf('type=bind,source=%s,target=%s', $path, $path);
      },
      $mounts);
  }

  public function getProxiedLinter(): ArcanistExternalLinter {
    if ($this->proxiedLinter === null) {
      throw new PhutilInvalidStateException('setProxiedLinter');
    }

    return $this->proxiedLinter;
  }

  public function getProxiedLinterExecutableCommand(): PhutilCommandString {
    $linter = $this->getProxiedLinter();

    if ($linter->shouldUseInterpreter()) {
      $interpreter = $linter->getInterpreter();
    } else {
      $interpreter = null;
    }

    // NOTE: We can't call `$linter->getExecutableCommand()` as that will
    // attempt to execute the command directly (bypassing Docker) in
    // @{method:ArcanistExternalLinter::checkBinaryConfiguration}.
    if ($interpreter !== null) {
      return csprintf('%s %s', $interpreter, $linter->getBinary());
    } else {
      return csprintf('%s', $linter->getBinary());
    }
  }

  /**
   * Mount an additional path into the Docker container.
   *
   * By default, the project root directory is mounted into the Docker
   * container. If the external linter requires access to any files outside of
   * the project root directory, they must be mounted explicitly.
   */
  public function mount(string $path): string {
    Filesystem::assertExists($path);
    $this->mounts[] = $path;

    return $path;
  }

  public function setImage(string $image) {
    $this->image = $image;
    return $this;
  }

  /**
   * Determine whether calls to the external linter should be proxied through a
   * Docker container.
   *
   * The "proxy external commands through a Docker container" behavior is
   * currently opt-out (i.e. enabled by default). Users can opt-out to the
   * behavior by setting the environment variable `DOCKER_LINTER_PROXY` to `no`.
   */
  public function shouldProxy(): bool {
    if ($this->shouldProxy !== null) {
      return $this->shouldProxy;
    }

    switch (getenv(self::ENV_SHOULD_PROXY)) {
      case 'no':
        return false;

      case 'yes':
        return true;

      case false:
        break;

      default:
        throw new ArcanistUsageException(
          pht(
            "Unexpected value for environment variable '%s'.",
            self::ENV_SHOULD_PROXY));
    }

    return true;
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

  public function setShouldProxy(bool $should_proxy) {
    $this->shouldProxy = $should_proxy;
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
      '--entrypoint=',
      '--rm',
      sprintf('--workdir=%s', $project_root),
    ];

    foreach ($this->getMounts() as $mount) {
      $flags[] = '--mount='.$mount;
    }

    return $flags;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    return $this->getProxiedLinter()->parseLinterOutput($path, $err, $stdout, $stderr);
  }

  protected function getPathArgumentForLinterFuture($path): PhutilCommandString {
    return $this->getProxiedLinter()->getPathArgumentForLinterFuture($path);
  }

  protected function buildFutures(array $paths): array {
    if (!$this->shouldProxy()) {
      return $this->getProxiedLinter()->buildFutures($paths);
    }

    $bin = csprintf(
      '%C %Ls %s %C %Ls',
      $this->getExecutableCommand(),
      $this->getCommandFlags(),
      $this->getImage(),
      $this->getProxiedLinterExecutableCommand(),
      $this->getProxiedLinter()->getCommandFlags());
    $futures = [];

    // Check docker image exists
    $image_future = new ExecFuture(
      '%C image inspect %s',
      $this->getExecutableCommand(),
      $this->getImage()
    );
    $image_future->setCWD($this->getProjectRoot());

    try {
      $image_future->resolvex();
    } catch (CommandException $e) {
      // Try to pull image
      $pull_future = new ExecFuture(
        '%C pull %s',
        $this->getExecutableCommand(),
        $this->getImage()
      );
      $pull_future->setCWD($this->getProjectRoot());
      try {
        $pull_future->resolvex();
      } catch (CommandException $e) {
        throw new ArcanistMissingLinterException(
          pht(
            "Docker image '%s' does not exist and could not be pulled. ".
            "Please check the image name or build the image and try again.",
            $this->getImage())
        );
      }
    }

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
