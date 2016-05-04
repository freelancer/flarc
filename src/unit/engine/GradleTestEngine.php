<?php

/**
 * Runs and collects results from Gradle Test
 *
 * This engine runs `./gradlew test` and collects the XML xUnit test results
 * from the folder specified by the configuration `unit.gradle.results-dir`.
 *
 * Unlike other test runners, the Gradle Test runner runs all the tests at
 * once. There may be lag between starting tests and seeing `arc unit` output.
 *
 * Some design considerations on why we do not support per-changed-file:
 *   - invoking `./gradlew test` has a long startup cost
 *   - `./gradlew test` only supports a single glob pattern using `--test`
 *
 * This could be addressed with a custom test task but this would require
 * projects to configure a custom test task which sucks.
 */
final class GradleTestEngine extends ArcanistUnitTestEngine {

  /**
   * The current implementation runs all the tests everytime.
   *
   * This engine does not support yet support testing only affected files,
   * though we could consider it in the future.
   */
  protected function supportsRunAllTests() {
    return true;
  }

  public function run() {
    $root = $this->getWorkingCopy()->getProjectRoot();
    $configuration_manager = $this->getConfigurationManager();

    $results_directory = $configuration_manager->getConfigFromAnySource(
      'unit.gradle.results-directory',
      'build/test-results');
    $gradle_tasks = $configuration_manager->getConfigFromAnySource(
      'unit.gradle.tasks',
      array('test'));

    $results_directory = Filesystem::resolvePath($results_directory, $root);

    // Clear the results directory so that we only parse results of the
    // current run. This also ensures test results are not cached.
    Filesystem::remove($results_directory);

    Filesystem::assertExists($root.'/gradlew');
    Filesystem::assertIsFile($root.'/gradlew');
    // TODO: replace this with an upstream version eventually
    if (!is_executable($root.'/gradlew')) {
      throw new FilesystemException(
        $root.'/gradlew',
        pht('`%s` is not executable.', 'gradlew'));
    }

    id(new ExecFuture('./gradlew --rerun-tasks %Ls', $gradle_tasks))
      ->setCWD($root)
      ->resolve();

    return $this->parseTestResults($results_directory);
  }

  private function parseTestResults($results_directory) {
    $parser = new ArcanistXUnitTestResultParser();
    $results = array();

    $test_reports = id(new FileFinder($results_directory))
      ->withSuffix('xml')
      ->find();

    foreach ($test_reports as $report) {
      $results[] = $parser->parseTestResults(
        Filesystem::readFile($results_directory.'/'.$report));
    }

    return array_mergev($results);
  }
}
