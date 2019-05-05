<?php

/**
 * @todo Use a mock linter instead of @{class:ArcanistPhpLinter}.
 */
final class ArcanistDockerContainerLinterProxyTestCase
  extends ArcanistExternalLinterTestCase {

  protected function getLinter(): ArcanistLinter {
    return new ArcanistDockerContainerLinterProxy();
  }

  public function testGetImage(): void {
    $linter = $this->getLinter();
    $image  = 'ubuntu';

    $linter->setImage($image);
    $this->assertEqual($image, $linter->getImage());
  }

  public function testGetProxiedLinter(): void {
    $linter  = $this->getLinter();
    $proxied = new ArcanistPhpLinter();

    $linter->setProxiedLinter($proxied);
    $this->assertEqual($proxied, $linter->getProxiedLinter());
  }

  public function testGetLinterPriority(): void {
    $proxied = new ArcanistPhpLinter();
    $linter  = $this->getLinter()->setProxiedLinter($proxied);

    $this->assertEqual(
      $proxied->getLinterPriority(),
      $linter->getLinterPriority());
  }

  public function testSetCustomSeverityMap(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testAddCustomSeverityMap(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testGetCacheVersion(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testCanRun(): void {
    $proxied = new ArcanistPhpLinter();
    $linter  = $this->getLinter()->setProxiedLinter($proxied);

    $this->assertEqual(
      $proxied->canRun(),
      $linter->canRun());
  }

  public function testGetLinterName(): void {
    $this->assertEqual(null, $this->getLinter()->getLinterName());
  }

  public function testGetVersion(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testGetLintSeverityMap(): void {
    $proxied = new ArcanistPhpLinter();
    $linter  = $this->getLinter()->setProxiedLinter($proxied);

    $this->assertEqual(
      $proxied->getLintSeverityMap(),
      $linter->getLintSeverityMap());
  }

  public function testGetLintNameMap(): void {
    $proxied = new ArcanistPhpLinter();
    $linter  = $this->getLinter()->setProxiedLinter($proxied);

    $this->assertEqual(
      $proxied->getLintNameMap(),
      $linter->getLintNameMap());
  }

  public function testGetCacheGranularity(): void {
    $proxied = new ArcanistPhpLinter();
    $linter  = $this->getLinter()->setProxiedLinter($proxied);

    $this->assertEqual(
      $proxied->getCacheGranularity(),
      $linter->getCacheGranularity());
  }

  public function testGetLinterConfigurationName(): void {
    $this->assertEqual(
      'docker-proxy',
      $this->getLinter()->getLinterConfigurationName());
  }

  public function testSetLinterConfigurationValue(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testCanCustomizeLintSeverities(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testGetPaths(): void {
    // TODO: Write test coverage for @{method:shouldLintBinaryFiles},
    // @{method:shouldLintDeletedFiles}, @{method:shouldLintDirectories} and
    // @{method:shouldLintSymbolicLinks}.
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testGetLintCodeFromLinterConfigurationKey(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testShouldExpectCommandErrors(): void {
    $proxied = new ArcanistPhpLinter();
    $linter  = $this->getLinter()->setProxiedLinter($proxied);

    $this->assertEqual(
      $proxied->shouldExpectCommandErrors(),
      $linter->shouldExpectCommandErrors());
  }

  public function testGetMandatoryFlags(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testGetPathArgumentForLinterFuture(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/docker-proxy/');
  }

}
