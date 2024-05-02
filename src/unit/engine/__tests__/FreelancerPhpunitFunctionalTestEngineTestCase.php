<?php


final class FreelancerPhpunitFunctionalTestEngineTestCase
  extends PhutilTestCase {

  private function createTestEngine() {
    $dir = 'testfiles';
    $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
      $dir,
      null,
      pht('Unit Test'));

    $configuration_manager = new ArcanistConfigurationManager();
    $configuration_manager->setWorkingCopyIdentity($working_copy);

    $configuration_manager->setRuntimeConfig(
      'unit.phpunit.test-directory',
      'test/');

    $configuration_manager->setRuntimeConfig(
      'unit.phpunit.source-directory',
      'src/');

    $engine = new FreelancerPhpunitFunctionalTestEngine();
    return $engine
      ->setConfigurationManager($configuration_manager)
      ->setWorkingCopy($working_copy);
  }

  public function testExtractingSimpleClassNameShouldReturnClassName() {
    $engine = $this->createTestEngine();
    $content = <<<PHP
<?php
/**
 * @covers ClassName
 */
class Test {}
PHP;
    $expected = ['ClassName'];
    $this->assertEqual($expected, $engine->extractAnnotatedClasses($content));
  }

  public function testIgnoringMethodSpecificAnnotationsShouldReturnEmptyArray() {
    $engine = $this->createTestEngine();
    $content = <<<PHP
<?php
class Test {
  /**
   * @covers ClassName::methodName
   */
  public function test() {}
}
PHP;
    $expected = ['ClassName'];
    $this->assertEqual($expected, $engine->extractAnnotatedClasses($content));
  }

  public function testExtractingNamespacedClassNameShouldReturnFullClassName() {
    $engine = $this->createTestEngine();
    $content = <<<PHP
<?php
/**
 * @covers \Namespace\SubNamespace\ClassName
 */
class Test {}
PHP;
    $expected = ['\Namespace\SubNamespace\ClassName'];
    $this->assertEqual($expected, $engine->extractAnnotatedClasses($content));
  }

  public function testIgnoringClassesStartingWithLowercaseShouldReturnEmptyArray() {
    $engine = $this->createTestEngine();
    $content = <<<PHP
<?php
class Test {
  /**
   * @covers ::methodName
   */
  public function test() {}
}
PHP;
    $expected = []; // Should ignore as it starts with a lowercase
    $this->assertEqual($expected, $engine->extractAnnotatedClasses($content));
  }

  public function testExtractingMultipleAnnotationsInOneFileShouldReturnAllClassNames() {
    $engine = $this->createTestEngine();
    $content = <<<PHP
<?php
/**
 * @covers ClassOne
 * @covers ClassTwo
 */
class Test {}
PHP;
    $expected = ['ClassOne', 'ClassTwo'];
    $this->assertEqual($expected, $engine->extractAnnotatedClasses($content));
  }

  public function testAnnotationFormattingVariationsShouldReturnAllClassNames() {
    $engine = $this->createTestEngine();
    $content = <<<PHP
<?php
/**
 * @covers    ClassThree
 * @covers\tClassFour
 */
class Test {}
PHP;
    $expected = ['ClassThree', 'ClassFour'];
    $this->assertEqual($expected, $engine->extractAnnotatedClasses($content));
  }

  public function testExtractingClassSixShouldReturnClassName() {
    $engine = $this->createTestEngine();
    $content = <<<PHP
<?php
/**
*   @coversDefaultClass ClassSix
 */
class Test {
/**
* @covers ::coverMe
 */
public function test() {}
}
PHP;
    $expected = ['ClassSix'];
    $this->assertEqual($expected, $engine->extractAnnotatedClasses($content));
  }
}
