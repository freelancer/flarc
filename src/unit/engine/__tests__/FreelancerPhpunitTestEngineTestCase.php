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

    $test_cases = array(
      array(
        array(),
        array(),
      ),
      array(
        array(
          'some/file.php',
        ),
        array(
          'some/file.php' => array(),
        ),
      ),
      array(
        array(
          'src/SomeClass.php',
        ),
        array(
          'src/SomeClass.php' => array(
            'test/SomeClassTest.php',
          ),
        ),
      ),
      array(
        array(
          'test/SomeTest.php',
        ),
        array(
          'test/SomeTest.php' => array(
            'test/SomeTest.php',
          ),
        ),
      ),
    );

    foreach ($test_cases as $test_case) {
      list($paths, $expected) = $test_case;

      $engine
        ->setSourceDirectory('src/')
        ->setTestDirectory('test/')
        ->setPaths($paths);

      $this->assertEqual($expected, $engine->getAffectedTests());
    }
  }

}
