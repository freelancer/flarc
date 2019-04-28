<?php

/**
 * This class is similar to @{class:ArcanistExternalLinter}, but allows paths
 * to be passed to the external linter in batches instead of individually. This
 * will generally improve linter performance when the external linter has a
 * relatively slow start-up. See T67044 for one such example.
 */
abstract class ArcanistBatchExternalLinter extends ArcanistExternalLinter {

  private $batchSize = PHP_INT_MAX;
  private $futures = [];

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
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  final public function willLintPaths(array $paths): void {
    $bin = csprintf(
      '%C %Ls',
      $this->getExecutableCommand(),
      $this->getCommandFlags());

    $this->futures = (new FutureIterator([]))
      ->limit($this->getFuturesLimit());

    foreach (array_chunk($paths, $this->batchSize) as $chunk) {
      $path_arguments = array_map(
        function (string $path): string {
          $disk_path = $this->getEngine()->getFilePathOnDisk($path);
          return $this->getPathArgumentForLinterFuture($disk_path);
        },
        $chunk);

      $future = new ExecFuture('%C %C', $bin, implode(' ', $path_arguments));
      $future->setCWD($this->getProjectRoot());
      $this->futures->addFuture($future);
    }
  }

  final public function didLintPaths(array $paths): void {
    $futures = [];

    foreach ($this->futures as $future) {
      $this->resolveFuture(null, $future);
      $futures[] = $future;
    }

    $this->futures = [];
    $this->didResolveLinterFutures($futures);
  }

}
