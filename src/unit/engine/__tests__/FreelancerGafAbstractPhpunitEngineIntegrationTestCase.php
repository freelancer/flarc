<?php

/**
 * Base class for PHPUnit engine integration tests.
 *
 * Provides reusable infrastructure for testing all PHPUnit engine
 * implementations (FreelancerPhpunitTestEngine, Gaf variants, etc.).
 *
 * Features:
 * - Mock project creation with configurable directory structure
 * - Mock PHPUnit binary that generates valid JUnit and Clover XML
 * - Composer files setup (composer.lock, vendor/composer/installed.json)
 * - Custom assertions for file counts, patterns, and hash validation
 * - Automatic cleanup of temporary test projects
 *
 * Design principle: Test behavior, not implementation.
 * All helpers are implementation-agnostic and work across engine variants.
 */
abstract class FreelancerGafAbstractPhpunitEngineIntegrationTestCase
  extends PhutilTestCase {

  /** @var array $tempProjects */
  private $tempProjects = array();

  /**
   * Create a mock project structure.
   *
   * @param array $options {
   *   test_dirs: array,
   *   report_subdir: string,
   *   phpunit_config: string,
   * }
   * @return array Project configuration
   */
  protected function createMockProject(array $options = array()): array {
    $temp_project = Filesystem::createTemporaryDirectory();
    $this->tempProjects[] = $temp_project;

    $test_dirs = $options['test_dirs'] ?? array('tests');
    $report_subdir = $options['report_subdir'] ?? 'test-run';

    foreach ($test_dirs as $dir) {
      Filesystem::createDirectory($temp_project.'/'.$dir, 0755, true);
    }

    // Create PHPUnit configuration
    $this->createPhpunitConfig(
      $temp_project,
      $options['phpunit_config'] ?? null);

    // Create composer files
    $this->createComposerFiles($temp_project);

    // Create mock PHPUnit binary
    $phpunit_bin = $this->createMockPhpunitBinary($temp_project);

    $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
      $temp_project,
      null,
      pht('Unit Test'));

    $configuration_manager = new ArcanistConfigurationManager();
    $configuration_manager->setWorkingCopyIdentity($working_copy);
    $configuration_manager->setRuntimeConfig(
      'unit.phpunit.binary',
      $phpunit_bin);
    $configuration_manager->setRuntimeConfig(
      'unit.phpunit.config',
      $temp_project.'/phpunit.xml');
    $configuration_manager->setRuntimeConfig(
      'unit.phpunit.source-directory',
      $temp_project.'/tests/');
    $configuration_manager->setRuntimeConfig(
      'unit.phpunit.test-directory',
      $temp_project.'/tests/');
    $configuration_manager->setRuntimeConfig(
      'unit.phpunit.reports',
      $report_subdir.'/');
    $configuration_manager->setRuntimeConfig(
      'unit.phpunit.test-type',
      'test');

    return array(
      'project_root' => $temp_project,
      'working_copy' => $working_copy,
      'configuration_manager' => $configuration_manager,
      'phpunit_bin' => $phpunit_bin,
    );
  }

  /**
   * Create a test file with given content.
   *
   * @param string $project_root The project root directory
   * @param string $relative_path Relative path (e.g., 'tests/FooTest.php')
   * @param string $content Test file content (optional)
   * @return string Absolute path to created test file
   */
  protected function createTestFile(
    string $project_root,
    string $relative_path,
    string $content = null): string {

    $full_path = $project_root.'/'.$relative_path;
    Filesystem::createDirectory(dirname($full_path), 0755, true);

    if ($content === null) {
      $class_name = basename($relative_path, '.php');
      $content = "<?php\nclass {$class_name} extends ".
        "PHPUnit\\Framework\\TestCase {\n".
        "  public function testExample() {\n".
        "    \$this->assertTrue(true);\n".
        "  }\n}";
    }

    Filesystem::writeFile($full_path, $content);
    return $full_path;
  }

  /**
   * Create PHPUnit configuration file.
   */
  private function createPhpunitConfig(
    string $project_root,
    ?string $custom_config): void {

    if ($custom_config !== null) {
      $phpunit_xml = $custom_config;
    } else {
      $phpunit_xml = '<?xml version="1.0"?>
<phpunit bootstrap="vendor/autoload.php">
  <testsuites>
    <testsuite name="Test Suite">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
</phpunit>';
    }

    Filesystem::writeFile($project_root.'/phpunit.xml', $phpunit_xml);
  }

  /**
   * Create composer.lock and vendor structure.
   */
  private function createComposerFiles(string $project_root): void {
    $composer_lock = json_encode(array('packages' => array()));
    Filesystem::writeFile(
      $project_root.'/composer.lock',
      $composer_lock);

    Filesystem::createDirectory(
      $project_root.'/vendor/composer',
      0755,
      true);

    $installed_json = json_encode(array());
    Filesystem::writeFile(
      $project_root.'/vendor/composer/installed.json',
      $installed_json);
  }

  /**
   * Create mock PHPUnit binary that generates valid XML.
   */
  private function createMockPhpunitBinary(string $project_root): string {
    $phpunit_bin = $project_root.'/phpunit';
    $phpunit_script = '#!/usr/bin/env php
<?php
$junit_file = null;
$clover_file = null;
$test_file = null;

// Parse arguments - handle both orders:
// phpunit [args] test.php  OR  phpunit test.php [args]
foreach ($argv as $i => $arg) {
  if (strncmp($arg, "--log-junit=", 12) === 0) {
    $junit_file = substr($arg, 12);
  }
  if (strncmp($arg, "--coverage-clover=", 18) === 0) {
    $clover_file = substr($arg, 18);
  }
  if (substr($arg, -4) === ".php" && !str_contains($arg, "=")) {
    $test_file = $arg;
  }
}

if ($junit_file) {
  $xml = \'<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Test" tests="1" assertions="1" errors="0"
    failures="0" skipped="0" time="0.001">
    <testcase name="testExample" class="MockTest" time="0.001"/>
  </testsuite>
</testsuites>\';
  file_put_contents($junit_file, $xml);
}

if ($clover_file && $test_file) {
  $clover = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<coverage generated=\"1234567890\">
  <project timestamp=\"1234567890\">
    <file name=\"{$test_file}\">
      <line num=\"1\" type=\"stmt\" count=\"1\"/>
    </file>
  </project>
</coverage>";
  file_put_contents($clover_file, $clover);
}
exit(0);
';

    Filesystem::writeFile($phpunit_bin, $phpunit_script);
    chmod($phpunit_bin, 0755);

    return $phpunit_bin;
  }

  /**
   * Assert file count in directory matching pattern.
   *
   * @param string $directory Directory to check
   * @param string $pattern Glob pattern (e.g., '*.xml')
   * @param int $expected_count Expected number of files
   * @param string $message Assertion message
   */
  protected function assertFileCount(
    string $directory,
    string $pattern,
    int $expected_count,
    string $message): void {

    $files = Filesystem::listDirectory($directory);
    $matching_files = array_filter($files, function ($f) use ($pattern) {
      return fnmatch($pattern, $f);
    });

    $this->assertEqual(
      $expected_count,
      count($matching_files),
      $message.pht('. Found: %s', implode(', ', $matching_files)));
  }

  /**
   * Assert all filenames match a given pattern.
   *
   * @param array $filenames Array of filenames to check
   * @param string $pattern Regex pattern
   * @param string $message Assertion message
   */
  protected function assertAllFilesMatchPattern(
    array $filenames,
    string $pattern,
    $message = 'Files should match the expected pattern'): void {

    foreach ($filenames as $filename) {
      $this->assertTrue(
        preg_match($pattern, $filename) === 1,
        $message.pht(': %s', $filename));
    }
  }

  /**
   * Create and configure a PHPUnit test engine.
   *
   * @param array $project Project configuration
   * @param array $test_paths Array of test file paths to run
   * @return ArcanistUnitTestEngine Configured engine instance
   */
  protected function createEngine(
    array $project,
    array $test_paths): ArcanistUnitTestEngine {

    $engine = new FreelancerGafPhpunitTestEngine();
    $engine->setConfigurationManager($project['configuration_manager']);
    $engine->setWorkingCopy($project['working_copy']);
    $engine->setPaths($test_paths);

    return $engine;
  }

  /**
   * Run engine and return results with report directory paths.
   *
   * @param ArcanistUnitTestEngine $engine Configured engine
   * @param string $project_root Project root directory
   * @param string $report_subdir Report subdirectory
   * @return array Results and directory paths
   */
  protected function runEngine(
    ArcanistUnitTestEngine $engine,
    string $project_root,
    $report_subdir = 'test-run'): array {

    $results = $engine->run();

    return array(
      'results' => $results,
      'junit_dir' => $project_root.'/reports/'.$report_subdir.'/junit',
      'clover_dir' => $project_root.'/reports/'.$report_subdir.'/clover',
    );
  }

  /**
   * Assert files exist matching basename with unique hashes.
   *
   * @param string $directory Directory to check
   * @param string $basename Base filename (e.g., 'GetEndpointTest')
   * @param int $expected_count Expected number of files
   */
  protected function assertUniqueHashedFiles(
    string $directory,
    string $basename,
    int $expected_count): void {

    // Check file count
    $this->assertFileCount(
      $directory,
      $basename.'-*.xml',
      $expected_count,
      pht(
        'Should generate %d unique file(s) for "%s"',
        $expected_count,
        $basename));

    // Verify hash format
    $files = Filesystem::listDirectory($directory);
    $matching_files = array_filter($files, function ($f) use ($basename) {
      return strncmp($f, $basename.'-', strlen($basename) + 1) === 0;
    });

    $this->assertAllFilesMatchPattern(
      $matching_files,
      '/^'.preg_quote($basename, '/').'-[a-f0-9]{8}\.xml$/',
      pht('Files should follow pattern: %s-{8-char-hash}.xml', $basename));
  }

  /**
   * Clean up temporary projects after test execution.
   */
  protected function didRunTests(): void {
    foreach ($this->tempProjects as $project) {
      if (Filesystem::pathExists($project)) {
        Filesystem::remove($project);
      }
    }
    $this->tempProjects = array();
  }
}
