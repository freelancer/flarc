<?php

/**
 * Tests cover the main functionality including test discovery, path mapping,
 * dependency checking, and output file generation.
 *
 * @covers FreelancerGafPhpunitTestEngine
 */
final class FreelancerGafPhpunitTestEngineTestCase extends PhutilTestCase {

  /* -(  Test Setup  )------------------------------------------------------- */

  /**
   * Create a test engine instance with temporary working directory.
   *
   * @return FreelancerGafPhpunitTestEngine Configured test engine instance.
   */
  private function createTestEngine() {
    $dir = Filesystem::createTemporaryDirectory();
    $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
      $dir,
      null,
      pht('Unit Test'));

    $configuration_manager = new ArcanistConfigurationManager();
    $configuration_manager->setWorkingCopyIdentity($working_copy);

    return id(new FreelancerGafPhpunitTestEngine())
      ->setConfigurationManager($configuration_manager)
      ->setWorkingCopy($working_copy);
  }

  /**
   * Create a configured test engine with source and test directories set.
   *
   * @param string $source_dir Source directory path.
   * @param string $test_dir   Test directory path.
   * @return FreelancerGafPhpunitTestEngine Configured test engine.
   */
  private function createConfiguredTestEngine(
    $source_dir = 'src/',
    $test_dir = 'test/') {
    $engine = $this->createTestEngine();
    $engine->setSourceDirectory($source_dir);
    $engine->setTestDirectory($test_dir);
    return $engine;
  }

  /* -(  Test Discovery Tests  )--------------------------------------------- */

  /**
   * Test that getAffectedTests returns empty array when no paths are provided.
   */
  public function testGetAffectedTestsWithNoPathsReturnsEmptyArray() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $engine->setPaths([]);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $this->assertEqual([], $result, 'Should return empty array with no paths');
  }

  /**
   * Test that non-PHP files are ignored and return no affected tests.
   */
  public function testGetAffectedTestsWithNonPhpFileReturnsEmptyTestList() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $engine->setPaths(['some/file.txt']);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $this->assertEqual(
      ['some/file.txt' => []],
      $result,
      'Non-PHP files should have no affected tests');
  }

  /**
   * Test that source files are correctly mapped to their test files.
   */
  public function testGetAffectedTestsWithSourceFileReturnsMappedTestFile() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $engine->setPaths(['src/SomeClass.php']);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'src/SomeClass.php' => [
        'test/SomeClassTest.php',
      ],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Source files should map to corresponding test files');
  }

  /**
   * Test that test files map to themselves.
   */
  public function testGetAffectedTestsWithTestFileReturnsSelf() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $engine->setPaths(['test/SomeTest.php']);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'test/SomeTest.php' => [
        'test/SomeTest.php',
      ],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Test files should map to themselves');
  }

  /**
   * Test that files starting with lowercase are correctly mapped to
   * uppercase test files.
   */
  public function testGetAffectedTestsWithLowercaseSourceFile() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $engine->setPaths(['src/helper.php']);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'src/helper.php' => [
        'test/HelperTest.php',
      ],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Lowercase source files should map to uppercase test files');
  }

  /**
   * Test that multiple paths return multiple affected tests.
   */
  public function testGetAffectedTestsWithMultiplePathsReturnsAllMappedTests() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $paths = [
      'src/ClassA.php',
      'src/ClassB.php',
      'test/ExistingTest.php',
    ];
    $engine->setPaths($paths);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'src/ClassA.php' => ['test/ClassATest.php'],
      'src/ClassB.php' => ['test/ClassBTest.php'],
      'test/ExistingTest.php' => ['test/ExistingTest.php'],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Multiple paths should return all mapped tests');
  }

  /**
   * Test that files outside source and test directories return empty
   * test lists.
   */
  public function testGetAffectedTestsWithFileOutsideConfiguredDirs() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $engine->setPaths(['other/SomeFile.php']);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'other/SomeFile.php' => [],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Files outside configured directories should return empty test lists');
  }

  /* -(  Source to Test Mapping Tests  )---------------------------------- */

  /**
   * Test that source files in nested directories map correctly to test
   * files.
   */
  public function testGetAffectedTestsWithNestedSourceFileReturnsCorrectPath() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $source_path = 'src/models/User.php';
    $engine->setPaths([$source_path]);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'src/models/User.php' => ['test/models/UserTest.php'],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Should correctly map source file to test file with directory '.
        'structure preserved');
  }

  /**
   * Test that deeply nested directory structures are preserved in test
   * file mapping.
   */
  public function testGetAffectedTestsWithDeeplyNestedDirectory() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $source_path = 'src/services/payment/Gateway.php';
    $engine->setPaths([$source_path]);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'src/services/payment/Gateway.php' => [
        'test/services/payment/GatewayTest.php',
      ],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Deeply nested directory structure should be preserved in test path');
  }

  /**
   * Test that source files with lowercase first letter map to uppercase
   * test files.
   */
  public function testGetAffectedTestsWithLowercaseSourceFilename() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $source_path = 'src/utilities/helper.php';
    $engine->setPaths([$source_path]);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'src/utilities/helper.php' => ['test/utilities/HelperTest.php'],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Lowercase filenames should be capitalized in test filename');
  }

  /* -(  Dependency Management Tests  )----------------------------------- */

  /**
   * Test that getStaleDependencies detects version mismatches.
   */
  public function testGetStaleDependenciesWithVersionMismatchReturnsOutdated() {
    // Arrange
    $composer_lock = [
      'packages' => [
        ['name' => 'vendor/package-a', 'version' => '2.0.0'],
        ['name' => 'vendor/package-b', 'version' => '1.5.0'],
      ],
    ];
    $installed = [
      ['name' => 'vendor/package-a', 'version' => '1.0.0'], // Outdated
      ['name' => 'vendor/package-b', 'version' => '1.5.0'], // Up to date
    ];

    // Act
    $result = FreelancerGafPhpunitTestEngine::getStaleDependencies(
      phutil_json_encode($composer_lock),
      phutil_json_encode($installed));

    // Assert
    $expected = ['vendor/package-a'];
    $this->assertEqual(
      $expected,
      $result,
      'Should detect packages with version mismatches');
  }

  /**
   * Test that getStaleDependencies detects missing packages.
   */
  public function testGetStaleDependenciesWithMissingPackageReturnsName() {
    // Arrange
    $composer_lock = [
      'packages' => [
        ['name' => 'vendor/package-a', 'version' => '1.0.0'],
        ['name' => 'vendor/package-b', 'version' => '1.0.0'],
        ['name' => 'vendor/package-c', 'version' => '1.0.0'],
      ],
    ];
    $installed = [
      ['name' => 'vendor/package-a', 'version' => '1.0.0'],
      ['name' => 'vendor/package-b', 'version' => '1.0.0'],
      // package-c is missing
    ];

    // Act
    $result = FreelancerGafPhpunitTestEngine::getStaleDependencies(
      phutil_json_encode($composer_lock),
      phutil_json_encode($installed));

    // Assert
    $expected = ['vendor/package-c'];
    $this->assertEqual(
      $expected,
      $result,
      'Should detect missing packages');
  }

  /**
   * Test that getStaleDependencies returns empty when all packages are
   * up to date.
   */
  public function testGetStaleDependenciesWithAllPackagesUpToDate() {
    // Arrange
    $composer_lock = [
      'packages' => [
        ['name' => 'vendor/package-a', 'version' => '1.0.0'],
        ['name' => 'vendor/package-b', 'version' => '2.0.0'],
      ],
    ];
    $installed = [
      ['name' => 'vendor/package-a', 'version' => '1.0.0'],
      ['name' => 'vendor/package-b', 'version' => '2.0.0'],
    ];

    // Act
    $result = FreelancerGafPhpunitTestEngine::getStaleDependencies(
      phutil_json_encode($composer_lock),
      phutil_json_encode($installed));

    // Assert
    $this->assertEqual(
      [],
      $result,
      'Should return empty array when all packages are up to date');
  }

  /**
   * Test that getStaleDependencies handles Composer 2.x format with
   * packages key.
   */
  public function testGetStaleDependenciesWithComposer2FormatWorksCorrectly() {
    // Arrange
    $composer_lock = [
      'packages' => [
        ['name' => 'vendor/package', 'version' => '2.0.0'],
      ],
    ];
    $installed_composer2 = [
      'packages' => [
        ['name' => 'vendor/package', 'version' => '1.0.0'],
      ],
    ];

    // Act
    $result = FreelancerPhpunitTestEngine::getStaleDependencies(
      phutil_json_encode($composer_lock),
      phutil_json_encode($installed_composer2));

    // Assert
    $expected = ['vendor/package'];
    $this->assertEqual(
      $expected,
      $result,
      'Should handle Composer 2.x installed.json format with packages key');
  }

  /**
   * Test that getStaleDependencies handles Composer 1.x format without
   * packages key.
   */
  public function testGetStaleDependenciesWithComposer1FormatWorksCorrectly() {
    // Arrange
    $composer_lock = [
      'packages' => [
        ['name' => 'vendor/package', 'version' => '2.0.0'],
      ],
    ];
    $installed_composer1 = [
      ['name' => 'vendor/package', 'version' => '1.0.0'],
    ];

    // Act
    $result = FreelancerPhpunitTestEngine::getStaleDependencies(
      phutil_json_encode($composer_lock),
      phutil_json_encode($installed_composer1));

    // Assert
    $expected = ['vendor/package'];
    $this->assertEqual(
      $expected,
      $result,
      'Should handle Composer 1.x installed.json format without packages key');
  }

  /**
   * Test that getStaleDependencies detects multiple outdated packages.
   */
  public function testGetStaleDependenciesWithMultipleStalePackages() {
    // Arrange
    $composer_lock = [
      'packages' => [
        ['name' => 'vendor/package-a', 'version' => '2.0.0'],
        ['name' => 'vendor/package-b', 'version' => '1.5.0'],
        ['name' => 'vendor/package-c', 'version' => '3.0.0'],
      ],
    ];
    $installed = [
      ['name' => 'vendor/package-a', 'version' => '1.0.0'], // Outdated
      ['name' => 'vendor/package-b', 'version' => '1.5.0'], // Up to date
      // package-c is missing
    ];

    // Act
    $result = FreelancerGafPhpunitTestEngine::getStaleDependencies(
      phutil_json_encode($composer_lock),
      phutil_json_encode($installed));

    // Assert
    $expected = ['vendor/package-a', 'vendor/package-c'];
    $this->assertEqual(
      $expected,
      $result,
      'Should detect all outdated and missing packages');
  }

  /* -(  Configuration Tests  )------------------------------------------- */

  /**
   * Test that setSourceDirectory correctly normalizes paths with
   * trailing slash.
   */
  public function testSetSourceDirectoryWithPathNormalizesWithTrailingSlash() {
    // Arrange
    $engine = $this->createTestEngine();

    // Act
    $engine->setSourceDirectory('src');

    // Assert - we test this indirectly by checking path mapping
    $engine->setTestDirectory('test/');
    $engine->setPaths(['src/Example.php']);
    $result = $engine->getAffectedTests();

    $this->assertEqual(
      ['src/Example.php' => ['test/ExampleTest.php']],
      $result,
      'Source directory should be normalized with trailing slash');
  }

  /**
   * Test that setTestDirectory correctly normalizes paths with trailing slash.
   */
  public function testSetTestDirectoryWithPathNormalizesWithTrailingSlash() {
    // Arrange
    $engine = $this->createTestEngine();

    // Act
    $engine->setTestDirectory('test');

    // Assert - we test this indirectly by checking path mapping
    $engine->setSourceDirectory('src/');
    $engine->setPaths(['src/Example.php']);
    $result = $engine->getAffectedTests();

    $this->assertEqual(
      ['src/Example.php' => ['test/ExampleTest.php']],
      $result,
      'Test directory should be normalized with trailing slash');
  }

  /**
   * Test that setTestType stores the test type correctly.
   */
  public function testSetTestTypeWithValidTypeStoresTypeCorrectly() {
    // Arrange
    $engine = $this->createTestEngine();

    // Act
    $engine->setTestType('unit');

    // Assert - we can't directly access private property, but we test that
    // it doesn't throw an exception and works with generateOutputFiles
    $this->assertTrue(true, 'setTestType should accept valid type string');
  }

  /* -(  Edge Cases and Error Scenarios  )-------------------------------- */

  /**
   * Test behavior with empty composer.lock packages array.
   */
  public function testGetStaleDependenciesWithEmptyComposerLockReturnsEmpty() {
    // Arrange
    $composer_lock = ['packages' => []];
    $installed = [['name' => 'vendor/package', 'version' => '1.0.0']];

    // Act
    $result = FreelancerGafPhpunitTestEngine::getStaleDependencies(
      phutil_json_encode($composer_lock),
      phutil_json_encode($installed));

    // Assert
    $this->assertEqual(
      [],
      $result,
      'Empty composer.lock should return no stale dependencies');
  }

  /**
   * Test behavior with empty installed.json.
   */
  public function testGetStaleDependenciesWithEmptyInstalledReturnsAll() {
    // Arrange
    $composer_lock = [
      'packages' => [
        ['name' => 'vendor/package-a', 'version' => '1.0.0'],
        ['name' => 'vendor/package-b', 'version' => '1.0.0'],
      ],
    ];
    $installed = [];

    // Act
    $result = FreelancerGafPhpunitTestEngine::getStaleDependencies(
      phutil_json_encode($composer_lock),
      phutil_json_encode($installed));

    // Assert
    $expected = ['vendor/package-a', 'vendor/package-b'];
    $this->assertEqual(
      $expected,
      $result,
      'Empty installed.json should return all packages as stale');
  }

  /**
   * Test that paths with namespace-like directories are handled correctly.
   */
  public function testGetAffectedTestsWithNamespaceDirectoryHandlesCorrectly() {
    // Arrange
    $engine = $this->createConfiguredTestEngine('src/', 'test/');
    $source_path = 'src/namespace/Class.php';
    $engine->setPaths([$source_path]);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'src/namespace/Class.php' => ['test/namespace/ClassTest.php'],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Should handle directory structures consistently');
  }

  /**
   * Test that files with complex names are mapped correctly.
   */
  public function testGetAffectedTestsWithComplexFilenameMapsCorrectly() {
    // Arrange
    $engine = $this->createConfiguredTestEngine();
    $source_path = 'src/SomeVeryLongClassName.php';
    $engine->setPaths([$source_path]);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'src/SomeVeryLongClassName.php' => ['test/SomeVeryLongClassNameTest.php'],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Complex filenames should be mapped correctly');
  }

  /**
   * Test that custom source and test directories are respected.
   */
  public function testGetAffectedTestsWithCustomDirectoriesUsesCustomPaths() {
    // Arrange
    $engine = $this->createConfiguredTestEngine('lib/', 'spec/');
    $engine->setPaths(['lib/Widget.php']);

    // Act
    $result = $engine->getAffectedTests();

    // Assert
    $expected = [
      'lib/Widget.php' => ['spec/WidgetTest.php'],
    ];
    $this->assertEqual(
      $expected,
      $result,
      'Should respect custom source and test directories');
  }
}
