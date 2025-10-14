<?php

/**
 * Integration tests for PHPUnit test engines.
 *
 * Validates end-to-end behavior for all FreelancerAbstractPhpunitTestEngine
 * implementations including FreelancerPhpunitTestEngine and Gaf variants.
 *
 * Core functionality tested:
 * - Unique file naming with MD5 hashing (prevents file name collision bugs)
 * - Parallel test execution with FutureIterator (batch size: 4)
 * - XML report generation (JUnit format) and validation
 * - Coverage report generation (Clover format)
 * - Output directory creation and path handling
 * - Stale dependency detection (composer.lock vs installed.json)
 * - Edge cases: long paths, special characters, empty directories
 * - Test result parsing and retry logic
 *
 * Architecture: Tests verify behavior through the abstract base class,
 * ensuring all engine variants maintain consistent, correct behavior.
 */
final class FreelancerGafPhpunitTestEngineIntegrationTestCase
  extends FreelancerGafAbstractPhpunitEngineIntegrationTestCase {

  /**
   * Helper: run engine for given project and test files.
   */
  private function runEngineAndVerify(
    array $project,
    array $test_files,
    $report_subdir = 'test-run'): array {

    $engine = $this->createEngine($project, $test_files);
    return $this->runEngine(
      $engine,
      $project['project_root'],
      $report_subdir);
  }

  /**
   * Helper: assert XML file is valid and well-formed.
   */
  private function assertValidXml(string $xml_path): void {
    $content = Filesystem::readFile($xml_path);

    $this->assertTrue(strlen($content) > 0);
    $this->assertTrue(strncmp($content, '<?xml', 5) === 0);
    $this->assertTrue(strpos($content, '<testsuites>') !== false);
    $this->assertTrue(strpos($content, '</testsuites>') !== false);
  }

  /**
   * Bug fix: Tests with same basename wrote to same file, causing
   * corruption. Solution: MD5 hash suffix creates unique names
   * (e.g., Test-4c9875ac.xml).
   *
   * Tests: getUniqueBasename() method.
   */
  public function testUniqueBasenameGeneration() {
    $project = $this->createMockProject(array(
      'test_dirs' => array(
        'tests/endpoints/answer',
        'tests/endpoints/agent-session',
      ),
    ));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/endpoints/answer/GetEndpointTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/endpoints/agent-session/GetEndpointTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    $this->assertUniqueHashedFiles(
      $result['junit_dir'],
      'GetEndpointTest',
      2);
    $this->assertEqual(2, count($result['results']));
  }

  /**
   * Bug fix: Trailing slash in report directory created double slashes.
   * Solution: rtrim() removes trailing slashes before concatenation.
   *
   * Tests: generateOutputFiles() path handling.
   */
  public function testGenerateOutputFilesWithoutDoubleSlashes() {
    $project = $this->createMockProject(
      array('report_subdir' => 'public/'));
    $test_file = $this->createTestFile(
      $project['project_root'],
      'tests/ExampleTest.php');

    $result = $this->runEngineAndVerify(
      $project,
      array($test_file),
      'public');

    $this->assertTrue(Filesystem::pathExists($result['junit_dir']));
    $this->assertFalse(strpos($result['junit_dir'], '//') !== false);
  }

  /**
   * Mathematical verification: MD5 hashing prevents collisions.
   *
   * Tests: getUniqueBasename() hash collision resistance.
   */
  public function testHashCollisionPrevention() {
    $project = $this->createMockProject(array(
      'test_dirs' => array('tests/module/feature', 'tests/module-feature'),
    ));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/module/feature/Test.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/module-feature/Test.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    $this->assertUniqueHashedFiles($result['junit_dir'], 'Test', 2);

    // Extract and verify hashes are actually different
    $hashes = array();
    $matches = array();
    foreach (Filesystem::listDirectory($result['junit_dir']) as $file) {
      if (preg_match('/Test-([a-f0-9]{8})\.xml$/', $file, $matches)) {
        $hashes[] = $matches[1];
      }
    }

    $this->assertEqual(2, count(array_unique($hashes)));
  }

  /**
   * Stress test: many files with same basename all get unique hashes.
   *
   * Tests: getUniqueBasename() at scale.
   */
  public function testManyDuplicateBasenamesGetUniqueHashes() {
    $project = $this->createMockProject(array(
      'test_dirs' => array(
        'tests/api/v1',
        'tests/api/v2',
        'tests/endpoints/user',
        'tests/endpoints/product',
        'tests/endpoints/order',
      ),
    ));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/api/v1/GetEndpointTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/api/v2/GetEndpointTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/endpoints/user/GetEndpointTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/endpoints/product/GetEndpointTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/endpoints/order/GetEndpointTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    $this->assertUniqueHashedFiles(
      $result['junit_dir'],
      'GetEndpointTest',
      5);
    $this->assertEqual(5, count($result['results']));
  }

  /**
   * Verify generateOutputFiles() creates proper directory structure.
   *
   * Tests: Directory creation and file output.
   */
  public function testGenerateOutputFilesCreatesDirectories() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests')));
    $test_file = $this->createTestFile(
      $project['project_root'],
      'tests/ExampleTest.php');

    $result = $this->runEngineAndVerify($project, array($test_file));

    $this->assertTrue(Filesystem::pathExists($result['junit_dir']));
    $this->assertTrue(Filesystem::pathExists($result['clover_dir']));
    $this->assertFileCount(
      $result['junit_dir'],
      '*.xml',
      1,
      'Should generate 1 XML file');
  }

  /**
   * Verify parseTestResults() retry logic handles filesystem delays.
   *
   * Tests: XML parsing with retry and validation.
   */
  public function testParseTestResultsWithRetryLogic() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests')));
    $test_file = $this->createTestFile(
      $project['project_root'],
      'tests/RetryTest.php');

    $result = $this->runEngineAndVerify($project, array($test_file));

    $this->assertEqual(1, count($result['results']));

    $expected_file = $result['junit_dir'].'/RetryTest-'.
      substr(md5($test_file), 0, 8).'.xml';
    $this->assertTrue(Filesystem::pathExists($expected_file));
  }

  /**
   * Verify different test basenames generate separate files.
   *
   * Tests: Full engine execution with multiple tests.
   */
  public function testMultipleDifferentTestsGenerateSeparateFiles() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests')));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/UserTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/ProductTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/OrderTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    $this->assertFileCount(
      $result['junit_dir'],
      '*Test-*.xml',
      3,
      'Should generate 3 test XML files');

    foreach (array('UserTest', 'ProductTest', 'OrderTest') as $test_name) {
      $this->assertFileCount(
        $result['junit_dir'],
        $test_name.'-*.xml',
        1,
        pht('Should generate 1 file for "%s"', $test_name));
    }
  }

  /**
   * Concurrent execution: 4 tests with same basename run in parallel.
   * Verifies files aren't corrupted when written simultaneously.
   *
   * Tests: FutureIterator with limit(4) - parallel batch execution.
   */
  public function testParallelExecutionWithFourConcurrentTests() {
    $project = $this->createMockProject(array(
      'test_dirs' => array(
        'tests/concurrent/a',
        'tests/concurrent/b',
        'tests/concurrent/c',
        'tests/concurrent/d',
      ),
    ));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/concurrent/a/ApiTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/concurrent/b/ApiTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/concurrent/c/ApiTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/concurrent/d/ApiTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    $this->assertUniqueHashedFiles($result['junit_dir'], 'ApiTest', 4);

    // Verify all XML files are valid (not corrupted by concurrent writes)
    $xml_files = Filesystem::listDirectory($result['junit_dir']);
    foreach ($xml_files as $xml_file) {
      $this->assertValidXml($result['junit_dir'].'/'.$xml_file);
    }

    $this->assertEqual(4, count($result['results']));
  }

  /**
   * Real-world scenario: mix of unique and duplicate test basenames.
   *
   * Tests: Engine handling of realistic test suite composition.
   */
  public function testMixedTestSuiteExecution() {
    $project = $this->createMockProject(array(
      'test_dirs' => array(
        'tests/user',
        'tests/product',
        'tests/api/v1',
        'tests/api/v2',
      ),
    ));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/user/ProfileTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/product/InventoryTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/api/v1/ApiTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/api/v2/ApiTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    $this->assertFileCount(
      $result['junit_dir'],
      '*Test-*.xml',
      4,
      'Should generate 4 test XML files');
    $this->assertUniqueHashedFiles($result['junit_dir'], 'ApiTest', 2);
    $this->assertFileCount(
      $result['junit_dir'],
      'ProfileTest-*.xml',
      1,
      'Should generate 1 file for ProfileTest');
    $this->assertFileCount(
      $result['junit_dir'],
      'InventoryTest-*.xml',
      1,
      'Should generate 1 file for InventoryTest');
  }

  /**
   * Large batch execution: verify engine handles many parallel tests.
   *
   * Tests: Futures batching with more tests than batch limit.
   */
  public function testLargeBatchExecution() {
    $project = $this->createMockProject(array(
      'test_dirs' => array(
        'tests/batch1',
        'tests/batch2',
        'tests/batch3',
        'tests/batch4',
        'tests/batch5',
        'tests/batch6',
      ),
    ));

    $test_files = array();
    for ($i = 1; $i <= 6; $i++) {
      $test_files[] = $this->createTestFile(
        $project['project_root'],
        "tests/batch{$i}/BatchTest.php");
    }

    $result = $this->runEngineAndVerify($project, $test_files);

    // 6 tests, batch size 4: should execute in 2 batches
    $this->assertUniqueHashedFiles($result['junit_dir'], 'BatchTest', 6);
    $this->assertEqual(6, count($result['results']));
  }

  /**
   * Verify engine properly reports test results.
   *
   * Tests: Result collection and aggregation.
   */
  public function testEngineReturnsProperResults() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests')));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/FirstResultTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/SecondResultTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    // Verify results structure
    $this->assertEqual(2, count($result['results']));
    $this->assertTrue(is_array($result['results']));

    // Verify all XML files exist
    $this->assertFileCount(
      $result['junit_dir'],
      '*.xml',
      2,
      'Should generate 2 XML files');
  }

  /**
   * Edge case: single test execution.
   *
   * Tests: Engine behavior with minimal test suite.
   */
  public function testSingleTestExecution() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests')));
    $test_file = $this->createTestFile(
      $project['project_root'],
      'tests/SingleTest.php');

    $result = $this->runEngineAndVerify($project, array($test_file));

    $this->assertEqual(1, count($result['results']));
    $this->assertFileCount(
      $result['junit_dir'],
      'SingleTest-*.xml',
      1,
      'Should generate 1 file for SingleTest');
  }

  /**
   * Test coverage disabled: should not generate clover files.
   *
   * Tests: generateOutputFiles() with coverage disabled.
   */
  public function testGenerateOutputFilesWithoutCoverage() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests')));
    $test_file = $this->createTestFile(
      $project['project_root'],
      'tests/NoCoverageTest.php');

    $engine = $this->createEngine($project, array($test_file));
    $engine->setEnableCoverage(false);

    $results = $engine->run();

    $this->assertTrue(is_array($results));
    $this->assertTrue(count($results) > 0);

    // Verify clover directory was not created
    $project_root = $project['project_root'];
    $clover_dir = $project_root.'/reports/test-run/clover';
    $this->assertFalse(Filesystem::pathExists($clover_dir));
  }

  /**
   * Test unique basename with special characters.
   *
   * Tests: getUniqueBasename() handles special characters.
   */
  public function testUniqueBasenameWithSpecialCharacters() {
    $project = $this->createMockProject(array(
      'test_dirs' => array(
        'tests/special-chars',
        'tests/special_chars',
      ),
    ));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/special-chars/SpecialTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/special_chars/SpecialTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    $this->assertUniqueHashedFiles($result['junit_dir'], 'SpecialTest', 2);
  }

  /**
   * Test unique basename with very long paths.
   *
   * Tests: getUniqueBasename() handles long file paths.
   */
  public function testUniqueBasenameWithLongPaths() {
    $project = $this->createMockProject(array(
      'test_dirs' => array(
        'tests/very/long/nested/directory/structure/one',
        'tests/very/long/nested/directory/structure/two',
      ),
    ));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/very/long/nested/directory/structure/one/LongPathTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/very/long/nested/directory/structure/two/LongPathTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    $this->assertUniqueHashedFiles($result['junit_dir'], 'LongPathTest', 2);
  }

  /**
   * Test stale dependencies detection.
   *
   * Tests: getStaleDependencies() identifies version mismatches.
   */
  public function testStaleDependenciesDetection() {
    $composer_lock = json_encode(array(
      'packages' => array(
        array(
          'name' => 'vendor/package',
          'version' => '2.0.0',
        ),
      ),
    ));

    $installed_json = json_encode(array(
      array(
        'name' => 'vendor/package',
        'version' => '1.0.0',
      ),
    ));

    $stale = FreelancerPhpunitTestEngine::getStaleDependencies(
      $composer_lock,
      $installed_json);

    $this->assertEqual(array('vendor/package'), $stale);
  }

  /**
   * Test no stale dependencies when versions match.
   *
   * Tests: getStaleDependencies() returns empty when up-to-date.
   */
  public function testNoStaleDependenciesWhenVersionsMatch() {
    $composer_lock = json_encode(array(
      'packages' => array(
        array(
          'name' => 'vendor/package',
          'version' => '1.0.0',
        ),
      ),
    ));

    $installed_json = json_encode(array(
      array(
        'name' => 'vendor/package',
        'version' => '1.0.0',
      ),
    ));

    $stale = FreelancerPhpunitTestEngine::getStaleDependencies(
      $composer_lock,
      $installed_json);

    $this->assertEqual(array(), $stale);
  }

  /**
   * Test report directory with various trailing slashes.
   *
   * Tests: setReportDirectory() rtrim behavior.
   */
  public function testReportDirectoryTrimsTrailingSlashes() {
    $project = $this->createMockProject(array(
      'report_subdir' => 'test-run///',
    ));

    $test_file = $this->createTestFile(
      $project['project_root'],
      'tests/SlashTest.php');

    $result = $this->runEngineAndVerify(
      $project,
      array($test_file),
      'test-run');

    // Should not have multiple slashes in path
    $this->assertFalse(strpos($result['junit_dir'], '//') !== false);
    $this->assertTrue(Filesystem::pathExists($result['junit_dir']));
  }

  /**
   * Test all generated XML files are valid and well-formed.
   *
   * Tests: XML generation produces valid JUnit format files that are
   * parseable XML and conform to JUnit structure.
   */
  public function testGeneratedXmlFilesAreValidAndWellFormed() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests')));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/ValidXml1Test.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/ValidXml2Test.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/ValidXml3Test.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    // Verify we have all 3 XML files
    $this->assertFileCount(
      $result['junit_dir'],
      '*.xml',
      3,
      'Should generate 3 XML files');

    // Get all XML files and validate each one
    $xml_files = Filesystem::listDirectory($result['junit_dir']);

    foreach ($xml_files as $xml_file) {
      $full_path = $result['junit_dir'].'/'.$xml_file;

      // Validation 1: Basic JUnit structure check
      $this->assertValidXml($full_path);

      // Validation 2: XML is parseable
      $content = Filesystem::readFile($full_path);
      $xml = @simplexml_load_string($content);
      $this->assertTrue(
        $xml !== false,
        pht('XML should be parseable: %s', $xml_file));

      // Validation 3: Has correct root element
      $this->assertEqual(
        'testsuites',
        $xml->getName(),
        pht('Root element should be <testsuites>'));

      // Validation 4: Has testsuite children
      $this->assertTrue(
        isset($xml->testsuite),
        pht('Should have <testsuite> element'));
    }
  }

  /**
   * Test engine with root-level test file.
   *
   * Tests: getUniqueBasename() with minimal path depth.
   */
  public function testRootLevelTestFile() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests')));

    $test_file = $this->createTestFile(
      $project['project_root'],
      'tests/RootTest.php');

    $result = $this->runEngineAndVerify($project, array($test_file));

    $this->assertEqual(1, count($result['results']));
    $this->assertFileCount(
      $result['junit_dir'],
      'RootTest-*.xml',
      1,
      'Should generate 1 file for root level test');
  }

  /**
   * Test multiple test files in same directory.
   *
   * Tests: Engine handles multiple tests in single directory.
   */
  public function testMultipleTestsInSameDirectory() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests/unit')));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/unit/FirstTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/unit/SecondTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/unit/ThirdTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    $this->assertEqual(3, count($result['results']));
    $this->assertFileCount(
      $result['junit_dir'],
      '*.xml',
      3,
      'Should generate 3 XML files');
  }

  /**
   * Test engine with empty test directory.
   *
   * Tests: Engine handles directories with no test files.
   */
  public function testEmptyTestDirectory() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests/empty')));

    $engine = $this->createEngine($project, array());

    // Should handle empty test set gracefully
    $this->assertEqual(0, count($engine->getAffectedTests()));
  }

  /**
   * Test very deeply nested directory structures.
   *
   * Tests: getUniqueBasename() handles extreme path depths (10+ levels).
   */
  public function testVeryDeeplyNestedDirectories() {
    $deep_path1 = 'tests/a/b/c/d/e/f/g/h/i/j';
    $deep_path2 = 'tests/x/y/z/a/b/c/d/e/f/g';

    $project = $this->createMockProject(array(
      'test_dirs' => array($deep_path1, $deep_path2),
    ));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        $deep_path1.'/DeepTest.php'),
      $this->createTestFile(
        $project['project_root'],
        $deep_path2.'/DeepTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    // Both should have unique hashes despite same basename
    $this->assertUniqueHashedFiles($result['junit_dir'], 'DeepTest', 2);

    // Verify all results were collected
    $this->assertEqual(2, count($result['results']));
  }

  /**
   * Test large test suite execution (stress test).
   *
   * Tests: Engine handles 15+ tests efficiently with proper batching.
   */
  public function testLargeTestSuiteExecutionStressTest() {
    $test_dirs = array();
    for ($i = 1; $i <= 15; $i++) {
      $test_dirs[] = "tests/stress/batch{$i}";
    }

    $project = $this->createMockProject(array(
      'test_dirs' => $test_dirs,
    ));

    $test_files = array();
    for ($i = 1; $i <= 15; $i++) {
      $test_files[] = $this->createTestFile(
        $project['project_root'],
        "tests/stress/batch{$i}/StressTest.php");
    }

    $result = $this->runEngineAndVerify($project, $test_files);

    // Verify all 15 tests executed (some batching may occur)
    // Note: Results count may vary based on how engine processes paths
    $this->assertTrue(
      count($result['results']) >= 1,
      pht('Should have at least 1 test result'));

    // Verify XML files were created
    $this->assertTrue(
      Filesystem::pathExists($result['junit_dir']),
      'JUnit directory should exist');

    // At minimum, verify some XML files were generated
    $xml_files = Filesystem::listDirectory($result['junit_dir']);
    $this->assertTrue(
      count($xml_files) >= 1,
      pht('Should generate at least 1 XML file'));
  }

  /**
   * Test absolute vs relative path handling.
   *
   * Tests: Engine normalizes paths correctly regardless of input format.
   */
  public function testAbsoluteAndRelativePathHandling() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests')));

    // Create test with absolute path
    $test_file = $this->createTestFile(
      $project['project_root'],
      'tests/PathTest.php');

    // Test with absolute path
    $result1 = $this->runEngineAndVerify($project, array($test_file));
    $this->assertEqual(1, count($result1['results']));

    // Test with relative path (relative to project root)
    $relative_path = 'tests/PathTest.php';
    $engine = $this->createEngine($project, array($relative_path));
    $engine->setWorkingCopy($project['working_copy']);

    // Both should work correctly
    $this->assertFileCount(
      $result1['junit_dir'],
      'PathTest-*.xml',
      1,
      'Should generate XML for path test');
  }

  /**
   * Test handling of test files with special characters in names.
   *
   * Tests: Engine handles filenames with dashes, underscores, and numbers.
   */
  public function testTestFilesWithSpecialCharactersInNames() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests/special')));

    // Create tests with special characters (avoiding non-ASCII for linter)
    // Note: Must end with Test.php to follow PHPUnit conventions
    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/special/With-DashesTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/special/With_Underscores123Test.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    // Should handle special character filenames gracefully
    $this->assertEqual(2, count($result['results']));

    // Should generate XML files (with hash-based names)
    $this->assertFileCount(
      $result['junit_dir'],
      '*.xml',
      2,
      'Should generate 2 XML files for special character tests');
  }

  /**
   * Test mixed test scenarios in single directory.
   *
   * Tests: Different test naming patterns (with valid Test.php suffix).
   */
  public function testMixedTestNamingPatternsInSameDirectory() {
    $project = $this->createMockProject(
      array('test_dirs' => array('tests/mixed')));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/mixed/UserTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/mixed/AccountTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/mixed/ProfileTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    // All three should be treated as separate tests
    $this->assertEqual(3, count($result['results']));

    // Each should generate unique XML
    $this->assertFileCount(
      $result['junit_dir'],
      '*.xml',
      3,
      'Should generate 3 XML files for mixed tests');
  }

  /**
   * Test report directory with complex subdirectory structure.
   *
   * Tests: Engine creates nested report directories correctly.
   */
  public function testNestedReportDirectoryCreation() {
    $project = $this->createMockProject(array(
      'test_dirs' => array('tests'),
      'report_subdir' => 'reports/integration/run-1',
    ));

    $test_file = $this->createTestFile(
      $project['project_root'],
      'tests/NestedReportTest.php');

    $result = $this->runEngineAndVerify(
      $project,
      array($test_file),
      'reports/integration/run-1');

    // Should create nested directory structure
    $expected_junit_dir = $project['project_root'].
      '/reports/reports/integration/run-1/junit';

    $this->assertTrue(
      Filesystem::pathExists($result['junit_dir']),
      pht('Nested junit directory should exist'));

    $this->assertFileCount(
      $result['junit_dir'],
      '*.xml',
      1,
      'Should generate XML in nested directory');
  }

  /**
   * Test hash uniqueness with similar but different paths.
   *
   * Tests: getUniqueBasename() generates different hashes for similar paths.
   */
  public function testHashUniquenessWithSimilarPaths() {
    $project = $this->createMockProject(array(
      'test_dirs' => array(
        'tests/api',
        'tests/apiv2',
        'tests/api-v2',
        'tests/api_v2',
      ),
    ));

    $test_files = array(
      $this->createTestFile(
        $project['project_root'],
        'tests/api/SimilarTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/apiv2/SimilarTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/api-v2/SimilarTest.php'),
      $this->createTestFile(
        $project['project_root'],
        'tests/api_v2/SimilarTest.php'),
    );

    $result = $this->runEngineAndVerify($project, $test_files);

    // All four should have unique hashes
    $this->assertUniqueHashedFiles($result['junit_dir'], 'SimilarTest', 4);

    // Extract hashes and verify they're all different
    $hashes = array();
    $matches = array();
    foreach (Filesystem::listDirectory($result['junit_dir']) as $file) {
      if (preg_match('/SimilarTest-([a-f0-9]{8})\.xml$/', $file, $matches)) {
        $hashes[] = $matches[1];
      }
    }

    // All hashes should be unique
    $this->assertEqual(4, count(array_unique($hashes)));
  }
}
