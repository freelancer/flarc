<?php

final class ArcanistESLintLinter extends ArcanistBatchExternalLinter {

  private $cacheFile;
  private $config;
  private $env;
  private $parserOptionsTsconfigRootDir;
  private $resolvePluginsRelativeTo;
  private $noInlineConfig;

  public function getInfoName() {
    return 'ESLint';
  }

  public function getInfoURI() {
    return 'http://eslint.org/';
  }

  public function getInfoDescription() {
    return pht(
      'The pluggable linting utility for %s and %s.',
      'JavaScript',
      'JSX');
  }

  public function getLinterName() {
    return 'ESLint';
  }

  public function getLinterConfigurationName() {
    return 'eslint';
  }

  public function getDefaultBinary() {
    return 'eslint';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = [];
    $regex = '/^v(?<version>\d+(?:\.\d+){2})$/';

    if (!preg_match($regex, $stdout, $matches)) {
      return null;
    }

    return $matches['version'];
  }

  public function getInstallInstructions() {
    return pht(
      'Install %s with `%s`.',
      'ESLint',
      'npm install -g eslint');
  }

  public function getUpdateInstructions() {
    return pht(
      'Update %s with `%s`.',
      'ESLint',
      'npm update -g eslint');
  }

  protected function getMandatoryFlags() {
    $options = [];
    $options[] = '--format=json';

    if ($this->config !== null) {
      $options[] = '--config='.$this->config;
    } else {
      $options[] = '--no-eslintrc';
    }

    if ($this->env !== null) {
      $options[] = '--env='.$this->env;
    }

    if ($this->parserOptionsTsconfigRootDir !== null) {
      $options[] =
        '--parser-options={tsconfigRootDir:'.$this->parserOptionsTsconfigRootDir.'}';
    }

    if ($this->resolvePluginsRelativeTo !== null) {
      $options[] =
        '--resolve-plugins-relative-to='.$this->resolvePluginsRelativeTo;
    }

    // `eslint --cache=false` will wipe out the cache that is generated by
    // `eslint --cache` (by default, `.eslintcache`). To allow `eslint --cache`
    // to be executed outside of Arcanist without having `arc lint` wipe out
    // the cache every time that it runs, we set `--cache-location` to a
    // non-standard path. The exact path here doesn't really matter, so we just
    // point to the path of a temporary file which will be automatically
    // cleaned up afterwards.
    $this->cacheFile = new TempFile('eslintcache');
    $options[] = '--cache=false';
    $options[] = '--cache-location='.$this->cacheFile;

    if ($this->noInlineConfig) {
      $options[] = '--no-inline-config';
    }

    return $options;
  }

  public function getLinterConfigurationOptions(): array {
    $options = [
      'eslint.config' => [
        'type' => 'optional string',
        'help' => pht('%s configuration file.', 'ESLint'),
      ],
      'eslint.env' => [
        'type' => 'optional string',
        'help' => pht('Enables specific environments.'),
      ],
      'eslint.no-inline-config' => [
        'type' => 'optional bool',
        'help' => pht(
          'Prevents comments from changing configuration or rules.'),
      ],
      'eslint.parserOptions.tsconfigRootDir' => [
        'type' => 'optional string',
        'help' => pht(
          'The root directory for relative tsconfig paths specified in the project option'),
      ],
      'eslint.resolve-plugins-relative-to' => [
        'type' => 'optional string',
        'help' => pht(
          'A folder where plugins should be resolved from, CWD by default.'),
      ],
    ];

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value): void {
    switch ($key) {
      case 'eslint.config':
        $this->config = $value;
        return;

      case 'eslint.env':
        $this->env = $value;
        return;

      case 'eslint.parserOptions.tsconfigRootDir':
        $this->parserOptionsTsconfigRootDir = $value;
        return;

      case 'eslint.resolve-plugins-relative-to':
        $this->resolvePluginsRelativeTo = $value;
        return;

      case 'eslint.no-inline-config':
        $this->noInlineConfig = $value;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  private static function hasRangeOverlap(array $ranges, array $range_to_check): bool {
    foreach ($ranges as $range) {
      if (max($range[0], $range_to_check[0]) <= min($range[1], $range_to_check[1])) {
        return true;
      }
    }

    return false;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $files = [];
    $results = [];
    $fix_ranges = [];

    try {
      $files = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          "`%s` returned unparseable output:\n\n%s\n%s",
          'eslint',
          $stdout,
          $stderr),
        $ex);
    }

    foreach ($files as $file) {
      foreach ($file['messages'] as $message) {
        $result = id(new ArcanistLintMessage())
          ->setPath($file['filePath'])
          ->setChar($message['column'])
          ->setDescription($message['message']);

        if (isset($message['line'])) {
          $result->setLine($message['line']);
        }

        switch ($message['severity']) {
          case 1:
            $result->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            break;

          case 2:
            $result->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;

          default:
            // This shouldn't be reached, but just in case...
            $result->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
            break;
        }

        if (idx($message, 'fatal', false)) {
          $result->setCode('fatal');
          $result->setName('ESLint Fatal');
        } else {
          $result->setCode($message['ruleId']);
          $result->setName('ESLint '.$message['ruleId']);
        }

        if (isset($message['fix'])) {
          list($start_offset, $end_offset) = $message['fix']['range'];
          if (!self::hasRangeOverlap($fix_ranges, [$start_offset, $end_offset])) {
            $fix_ranges[] = [$start_offset, $end_offset];
            $result->setOriginalText(
              substr(
                $file['source'],
                $start_offset,
                $end_offset - $start_offset));
            $result->setReplacementText($message['fix']['text']);
          } else {
            $result->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            $result->setDescription(
              'This line has a that fix conflicts with another fix. Please run `arc lint` again after applying the fixes: '.$message['message']);
          }
        }

        $results[] = $result;
      }
    }

    return $results;
  }
}
