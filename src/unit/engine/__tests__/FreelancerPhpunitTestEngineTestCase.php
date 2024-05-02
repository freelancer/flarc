<?php

final class FreelancerPhpunitTestEngineTestCase extends PhutilTestCase {

  private function createTestEngine() {
    $dir = Filesystem::createTemporaryDirectory();
    $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
      $dir,
      null,
      pht('Unit Test'));

    $configuration_manager = new ArcanistConfigurationManager();
    $configuration_manager->setWorkingCopyIdentity($working_copy);

    return id(new FreelancerPhpunitTestEngine())
      ->setConfigurationManager($configuration_manager)
      ->setWorkingCopy($working_copy);
  }

  public function testGetAffectedTests() {
    $engine = $this->createTestEngine();

    $test_cases = [
      [
        [],
        [],
      ],
      [
        [
          'some/file.php',
        ],
        [
          'some/file.php' => [],
        ],
      ],
      [
        [
          'src/SomeClass.php',
        ],
        [
          'src/SomeClass.php' => [
            'test/SomeClassTest.php',
          ],
        ],
      ],
      [
        [
          'test/SomeTest.php',
        ],
        [
          'test/SomeTest.php' => [
            'test/SomeTest.php',
          ],
        ],
      ],
    ];

    foreach ($test_cases as $test_case) {
      list($paths, $expected) = $test_case;

      $engine->setSourceDirectory('src/');
      $engine->setTestDirectory('test/');
      $engine->setPaths($paths);

      $this->assertEqual($expected, $engine->getAffectedTests());
    }
  }


  public function testGetStaleDependencies() {
    $test_cases = [
      [
        [
          'packages' => [
            [
              'name' => 'A',
              'version' => '1.1.0',
            ],
            [
              'name' => 'B',
              'version' => '1.0.0',
            ],
            [
              'name' => 'C',
              'version' => '1.0.1',
            ],
          ],
        ],
        [
          [
            'name' => 'A',
            'version' => '1.0.1',
          ],
          [
            'name' => 'B',
            'version' => '1.0.0',
          ],
        ],
        ['A', 'C'],
      ],
    ];

    foreach ($test_cases as $test_case) {
      list($composer_lock, $installed, $expected) = $test_case;
      $composer_lock = phutil_json_encode($composer_lock);
      $installed = phutil_json_encode($installed);
      $this->assertEqual(
        $expected,
        FreelancerPhpunitTestEngine::getStaleDependencies(
          $composer_lock,
          $installed));
    }
  }
}
