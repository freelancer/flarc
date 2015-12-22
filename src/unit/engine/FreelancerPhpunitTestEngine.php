<?php

/**
 * PHPUnit test engine.
 *
 * This is intended to be an alternative to the upstream `PhpunitTestEngine`
 * test engine which is tailored for a very specific workflow. Specifically,
 * the following assumptions have been made:
 *
 *   # All source files are located in a "source directory" and all test files
 *     are located in a "test directory".
 *   # The directory structure of the test directory mirrors the directory
 *     structure of the source directory.
 *   # All source files match the regular expression `/\.php$/`.
 *   # All test files match the regular expression `/Test\.php$/`.
 *   # The unit tests for a given source file can be found at the same location
 *     in the test directory, appending `Test` to the filename (before the file
 *     extension). If the source file begins with a lowercase character, the
 *     test file should begin with an uppercase character. The reason for this
 *     is that tests should always be in a class, and the name of the test file
 *     should match the name of the test class.
 *
 * This is probably reasonable to be upstreamed at some stage, but probably
 * only after [[https://secure.phabricator.com/T5568 | T5568: Support
 * `.arcunit`, similar to `.arclint`]].
 */
final class FreelancerPhpunitTestEngine extends ArcanistUnitTestEngine {

  private $affectedTests = array();
  private $sourceDirectory;
  private $testDirectory;

  /**
   * Allows the unit test engine execute all tests with `arc unit --everything`.
   *
   * @return bool
   */
  protected function supportsRunAllTests() {
    return true;
  }

  public function run() {
    $this->setSourceDirectory(
      $this->getConfigPath('unit.phpunit.source-directory'));
    $this->setTestDirectory(
      $this->getConfigPath('unit.phpunit.test-directory'));

    if ($this->getRunAllTests()) {
      $this->setPaths(array($this->testDirectory));
    }
    $this->affectedTests = $this->getAffectedTests();

    if (empty($this->affectedTests)) {
      throw new ArcanistNoEffectException(pht('No tests to run.'));
    }

    $binary = $this->getBinaryPath('unit.phpunit.binary', 'phpunit');
    $config = $this->getConfigPath('unit.phpunit.config');

    $futures = array();
    $output  = array();

    foreach ($this->affectedTests as $source_path => $test_paths) {
      foreach ((array)$test_paths as $test_path) {
        if (!Filesystem::pathExists($test_path)) {
          continue;
        }

        $args = array();

        $clover_output = null;
        $json_output   = new TempFile();

        if ($config) {
          $args[] = '--configuration='.$config;
        }
        $args[] = '-d display_errors=stderr';
        $args[] = '--log-json='.$json_output;

        if ($this->getEnableCoverage() !== false) {
          $clover_output = new TempFile();
          $args[] = '--coverage-clover='.$clover_output;
        }

        $futures[$test_path] = new ExecFuture(
          '%s %Ls %s',
          $binary,
          $args,
          $test_path);

        $output[$test_path] = array(
          'clover' => $clover_output,
          'json' => $json_output,
        );
      }
    }

    $results = array();
    $futures = new FutureIterator($futures);

    foreach ($futures->limit(4) as $test => $future) {
      list($err, $stdout, $stderr) = $future->resolve();

      $results[] = $this->parseTestResults(
        $test,
        $output[$test]['json'],
        $output[$test]['clover'],
        $stderr);
    }

    return array_mergev($results);
  }

  /**
   * Retrieve all test files affected by the specified source files.
   *
   * Returns the paths to all test files which are affected by the source files
   * specified with @{method:setPaths}.
   *
   * @return list<string>
   */
  public function getAffectedTests() {
    $tests = array();

    foreach ($this->getPaths() as $path) {
      $tests[$path] = $this->getTestsForPath($path);
    }

    return $tests;
  }

  /**
   * Retrieve all tests affected by a specified path.
   *
   * This is an internal method which is only intended to be called from
   * @{method:getAffectedTests}.
   *
   * @param  string        The input path.
   * @return list<string>  A list of paths containing the tests affected by the
   *                       input path.
   */
  private function getTestsForPath($path) {
    $tests = array();

    if (!$this->sourceDirectory) {
      throw new PhutilInvalidStateException('setSourceDirectory');
    }
    if (!$this->testDirectory) {
      throw new PhutilInvalidStateException('setTestDirectory');
    }

    if (is_dir($path)) {
      if (FlarcFilesystem::isDescendant($path, $this->testDirectory)) {
        return $this->getTestsInDirectory($path);
      }

      if (FlarcFilesystem::isDescendant($path, $this->sourceDirectory)) {
        return $this->getTestsInDirectory(
          FlarcFilesystem::transposePath(
            $path,
            $this->sourceDirectory,
            $this->testDirectory));
      }
    }

    if (!preg_match('/\.php$/', $path)) {
      return array();
    }

    if (FlarcFilesystem::isDescendant($path, $this->testDirectory)) {
      if (preg_match('/Test\.php$/', $path)) {
        $tests[] = $path;
      }
    }

    if (FlarcFilesystem::isDescendant($path, $this->sourceDirectory)) {
      $tests[] = FlarcFilesystem::transposePath(
        dirname($path).'/'.ucfirst(
          preg_replace(
            '/^(.*)\.php$/',
            '$1Test.php',
            basename($path))),
        $this->sourceDirectory,
        $this->testDirectory);
    }

    return $tests;
  }

  /**
   * Get all test files contained within a given directory.
   *
   * Returns the paths to all test files contained within the specified
   * directory. A "test file" is any file which matches the regular expression
   * `/Test\.php$/`.
   *
   * This is an internal method which is only intended to be called from
   * @{method:getTestsForPath}.
   *
   * @param  string
   * @return list<string>
   */
  private function getTestsInDirectory($path) {
    $tests = array();

    $path = rtrim($path, '/');

    $files = id(new FileFinder($path))
      ->withType('f')
      ->withSuffix('php')
      ->find();

    foreach ($files as $file) {
      if (!preg_match('/Test\.php$/', $file)) {
        continue;
      }

      $tests[] = $path.'/'.$file;
    }

    return $tests;
  }

  /**
   * Set the source directory.
   *
   * Set the source directory. The source directory is the root directory which
   * contains source files. It is expected that the directory structure of the
   * test directory mirrors the directory structure of the source directory.
   *
   * @param  string
   * @return this
   */
  public function setSourceDirectory($source_directory) {
    $this->sourceDirectory = rtrim($source_directory, DIRECTORY_SEPARATOR).'/';
    return $this;
  }

  /**
   * Set the test directory.
   *
   * Set the test directory. The source directory is the root directory which
   * contains test files. It is expected that the directory structure of the
   * test directory mirrors the directory structure of the source directory.
   *
   * @param  string
   * @return this
   */
  public function setTestDirectory($test_directory) {
    $this->testDirectory = rtrim($test_directory, DIRECTORY_SEPARATOR).'/';
    return $this;
  }

  /**
   * Parse test results from PHPUnit JSON report.
   *
   * @param  string                        Path to test file.
   * @param  string                        Path to PHPUnit JSON report.
   * @param  string                        Path to PHPUnit Clover report.
   * @param  string                        Data written to `stderr`.
   * @return list<ArcanistUnitTestResult>
   */
  private function parseTestResults($path, $json, $clover, $stderr) {
    $results = Filesystem::readFile($json);

    return id(new ArcanistPhpunitTestResultParser())
      ->setEnableCoverage($this->getEnableCoverage())
      ->setProjectRoot($this->getProjectRoot())
      ->setCoverageFile($clover)
      ->setAffectedTests($this->affectedTests)
      ->setStderr($stderr)
      ->parseTestResults($path, $results);
  }


/* -(  Utility  )------------------------------------------------------------ */


  /**
   * Retrieve a path to an executable from the `.arcconfig` file.
   *
   * @param  string       The configuration key.
   * @param  string|null  The default value.
   * @return string       The path to the executable.
   */
  protected function getBinaryPath($key, $default = null) {
    $bin = $this
      ->getConfigurationManager()
      ->getConfigFromAnySource($key, $default);

    $binary_path = Filesystem::resolvePath($bin, $this->getProjectRoot());

    if (!Filesystem::binaryExists($binary_path)) {
      throw new ArcanistUsageException(
        pht(
          '%s does not seem to be installed at `%s`. Have you run `%s`?',
          'PHPUnit',
          $bin,
          'composer install'));
    }

    return $binary_path;
  }

  /**
   * Retrieve a path from the `.arcconfig` file.
   *
   * Retrieves a path from the arcanist configuration (`.arcconfig`) file,
   * resolving the path relative to the project root directory.
   *
   * @param  string       The configuration key.
   * @param  string|null  The default value.
   * @return string       The absolute path to the configuration file.
   */
  protected function getConfigPath($key, $default = null) {
    $path = $this->getConfigurationManager()->getConfigFromAnySource($key);

    if (!Filesystem::pathExists($path)) {
      throw new Exception(
        pht(
          "Path '%s' was not found for '%s'.",
          $path,
          $key));
    }

    return $path;
  }

  /**
   * Retrieve the project root directory.
   *
   * This is a convenience method to access the project root directory from the
   * working copy of the unit test engine.
   *
   * @return string  The project root directory.
   */
  public function getProjectRoot() {
    return $this->getWorkingCopy()->getProjectRoot();
  }

}
