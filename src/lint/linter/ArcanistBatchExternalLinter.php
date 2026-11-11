<?php

/**
 * This class is similar to @{class:ArcanistExternalLinter}, but allows paths
 * to be passed to the external linter in batches instead of individually. This
 * will generally improve linter performance when the external linter has a
 * relatively slow start-up. See T67044 for one such example.
 *
 * The lint engine hands linters their paths 32 at a time, calling
 * `willLintPaths()` and `didLintPaths()` once per chunk. Starting the external
 * linter once per chunk defeats batching: ESLint with type-aware rules spends
 * ~40s building the TypeScript program before it lints a single file. So on
 * the first chunk this linter lints every one of its paths, then hands each
 * chunk back only the messages for the paths the engine asked about.
 */
abstract class ArcanistBatchExternalLinter extends ArcanistExternalLinter {

  private $batchSize = PHP_INT_MAX;
  private $hasBatchSize = false;
  private $futures;
  private $messagesByPath;

  public function getLinterConfigurationOptions(): array {
    $options = [
      'batch-size' => [
        'type' => 'optional int',
        'help' => pht(
          'The maximum number of files to be passed to the external linter. '.
          'By default, all paths will be processed as a single batch.'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'batch-size':
        if (!is_int($value) || $value <= 0) {
          throw new Exception(
            pht('Batch size must be a positive integer.'));
        }

        $this->batchSize = $value;
        $this->hasBatchSize = true;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  final public function willLintPaths(array $paths): void {
    if ($this->futures !== null) {
      return;
    }

    $bin = csprintf(
      '%C %Ls',
      $this->getExecutableCommand(),
      $this->getCommandFlags());

    // Byte-limit batches run one at a time, so a large diff does not start
    // several copies of a memory-hungry linter. A set `batch-size` keeps the
    // old parallelism.
    $limit = $this->hasBatchSize ? $this->getFuturesLimit() : 1;
    $this->futures = (new FutureIterator([]))->limit($limit);

    foreach ($this->buildPathArgumentBatches($this->getPaths()) as $batch) {
      $future = new ExecFuture('%C %C', $bin, implode(' ', $batch));
      $future->setCWD($this->getProjectRoot());
      $this->futures->addFuture($future);
    }
  }

  final public function didLintPaths(array $paths): void {
    if ($this->messagesByPath === null) {
      $this->messagesByPath = [];
      $this->resolveAllFutures();
    }

    foreach ($paths as $path) {
      $key = $this->getMessagePathKey(
        $this->getEngine()->getFilePathOnDisk($path));

      foreach (idx($this->messagesByPath, $key, []) as $message) {
        $this->addLintMessage($message);
      }
      unset($this->messagesByPath[$key]);
    }
  }

  /**
   * @param list<string> $paths
   * @return list<list<string>>
   */
  private function buildPathArgumentBatches(array $paths): array {
    $batches = [];
    $batch = [];
    $batch_bytes = 0;
    $max_bytes = self::getMaxPathArgumentBytes();

    foreach ($paths as $path) {
      $disk_path = $this->getEngine()->getFilePathOnDisk($path);
      $argument = (string)$this->getPathArgumentForLinterFuture($disk_path);
      $argument_bytes = strlen($argument) + 1;

      $is_full = count($batch) >= $this->batchSize
        || $batch_bytes + $argument_bytes > $max_bytes;
      if (count($batch) !== 0 && $is_full) {
        $batches[] = $batch;
        $batch = [];
        $batch_bytes = 0;
      }

      $batch[] = $argument;
      $batch_bytes += $argument_bytes;
    }

    if (count($batch) !== 0) {
      $batches[] = $batch;
    }

    return $batches;
  }

  /**
   * The command runs as `sh -c <command>`. Linux caps one argument at 128 KiB;
   * macOS caps all arguments and the environment together at 1 MiB.
   */
  private static function getMaxPathArgumentBytes(): int {
    if (PHP_OS_FAMILY === 'Darwin') {
      return 512 * 1024;
    }

    return 96 * 1024;
  }

  /**
   * Resolves every batch and stores its messages by path. Messages for a path
   * this linter does not own are added at once, as the parent class does.
   */
  private function resolveAllFutures(): void {
    $own_paths = [];
    foreach ($this->getPaths() as $path) {
      $key = $this->getMessagePathKey(
        $this->getEngine()->getFilePathOnDisk($path));
      $own_paths[$key] = true;
    }

    $futures = [];
    foreach ($this->futures as $future) {
      $futures[] = $future;

      foreach ($this->resolveBatchFuture($future) as $message) {
        $key = $this->getMessagePathKey((string)$message->getPath());
        if (isset($own_paths[$key])) {
          $this->messagesByPath[$key][] = $message;
        } else {
          $this->addLintMessage($message);
        }
      }
    }

    $this->didResolveLinterFutures($futures);
  }

  /**
   * Same as @{method:ArcanistExternalLinter::resolveFuture}, but returns the
   * messages instead of adding them.
   *
   * @return list<ArcanistLintMessage>
   */
  private function resolveBatchFuture(Future $future): array {
    list($err, $stdout, $stderr) = $future->resolve();
    if ($err && !$this->shouldExpectCommandErrors()) {
      $future->resolvex();
    }

    $messages = $this->parseLinterOutput(null, $err, $stdout, $stderr);

    if ($err && $this->shouldExpectCommandErrors() && !$messages) {
      // We assume that if the future exits with a non-zero status and we
      // failed to parse any linter messages, then something must've gone wrong
      // during parsing.
      $messages = false;
    }

    if ($messages === false) {
      if ($err) {
        $future->resolvex();
      } else {
        throw new Exception(
          sprintf(
            "%s\n\nSTDOUT\n%s\n\nSTDERR\n%s",
            pht('Linter failed to parse output!'),
            $stdout,
            $stderr));
      }
    }

    return $messages;
  }

  /**
   * Normalises a path the same way @{method:ArcanistLinter::addLintMessage}
   * does, so a message matches the path the engine asked about.
   */
  private function getMessagePathKey(string $path): string {
    $root = $this->getProjectRoot();
    return Filesystem::readablePath(
      Filesystem::resolvePath($path, $root),
      $root);
  }

}
