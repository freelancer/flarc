<?php

/**
 * @phutil-external-symbol class Mockery
 */
final class ArcanistDockerContainerLinterProxyTestCase
  extends ArcanistExternalLinterTestCase {

  protected function didRunOneTest($test): void {
    // Clean up the `Mockery` container used by the current test and run any
    // verification tasks needed for our expectations.
    Mockery::close();
  }

  protected function getLinter(): ArcanistLinter {
    return new ArcanistDockerContainerLinterProxy();
  }

  protected function getLinterWithMockProxiedLinter(): ArcanistLinter {
    $linter = $this->getLinter();

    $engine = new ArcanistUnitTestableLintEngine();
    $linter->setEngine($engine);

    $mock = Mockery::mock(ArcanistExternalLinter::class);
    $mock->makePartial();
    $mock->shouldAllowMockingProtectedMethods();
    $linter->setProxiedLinter($mock);

    return $linter;
  }

  public function testGetImage(): void {
    $linter = $this->getLinterWithMockProxiedLinter();

    $image = 'ubuntu';
    $linter->setImage($image);

    $this->assertEqual($image, $linter->getImage());
  }

  public function testGetProxiedLinter(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = Mockery::mock(ArcanistExternalLinter::class);

    $engine = new ArcanistUnitTestableLintEngine();
    $linter->setEngine($engine);
    $linter->setProxiedLinter($proxied);

    $this->assertEqual($proxied, $linter->getProxiedLinter());
    $this->assertEqual($engine, $proxied->getEngine());
  }

  public function testGetLinterPriority(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $priority = 1.0;
    $proxied->expects()->getLinterPriority()->andReturn($priority);

    $this->assertEqual($priority, $linter->getLinterPriority());
  }

  public function testSetCustomSeverityMap(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $code = ArcanistPhpLinter::LINT_PARSE_ERROR;
    $severity = ArcanistLintSeverity::SEVERITY_ADVICE;
    $linter->setCustomSeverityMap([$code => $severity]);

    $this->assertEqual($severity, $proxied->getLintMessageSeverity($code));
  }

  public function testAddCustomSeverityMap(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $code = ArcanistPhpLinter::LINT_PARSE_ERROR;
    $severity = ArcanistLintSeverity::SEVERITY_ADVICE;
    $linter->addCustomSeverityMap([$code => $severity]);

    $this->assertEqual($severity, $proxied->getLintMessageSeverity($code));
  }

  public function testGetCacheVersion(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $version = '123';
    $proxied->expects()->getCacheVersion()->andReturn($version);

    $this->assertEqual($version, $linter->getCacheVersion());
  }

  public function testCanRun(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $proxied->expects()->canRun()->andReturns(true);

    $this->assertTrue($linter->canRun());
  }

  public function testGetLinterName(): void {
    $linter = $this->getLinterWithMockProxiedLinter();
    $this->assertEqual(null, $linter->getLinterName());
  }

  public function testGetVersion(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testGetLintSeverityMap(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $map = [
      1 => ArcanistLintSeverity::SEVERITY_ADVICE,
    ];
    $proxied->expects()->getLintSeverityMap()->andReturns($map);

    $this->assertEqual($map, $linter->getLintSeverityMap());
  }

  public function testGetLintNameMap(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $map = [
      1 => pht('Syntax Error'),
    ];
    $proxied->expects()->getLintNameMap()->andReturns($map);

    $this->assertEqual($map, $linter->getLintNameMap());
  }

  public function testGetCacheGranularity(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $granularity = ArcanistLinter::GRANULARITY_GLOBAL;
    $proxied->expects()->getCacheGranularity()->andReturns($granularity);

    $this->assertEqual($granularity, $linter->getCacheGranularity());
  }

  public function testGetLinterConfigurationName(): void {
    $linter = $this->getLinterWithMockProxiedLinter();
    $this->assertEqual('docker-proxy', $linter->getLinterConfigurationName());
  }

  public function testSetLinterConfigurationValue(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $config = [
      'foo' => 'bar',
    ];
    foreach ($config as $key => $value) {
      $proxied->expects()->setLinterConfigurationValue($key, $value);
    }

    $linter->setLinterConfigurationValue('docker-proxy.linter.config', $config);
    $this->assertTrue(true);
  }

  public function testCanCustomizeLintSeverities(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $proxied->expects()->canCustomizeLintSeverities()->andReturns(false);

    $config_options = $linter->getLinterConfigurationOptions();
    $this->assertFalse(isset($config_options['severity']));
  }

  public function testCanCustomizeLintSeveritiesWithoutProxiedLinter(): void {
    $linter  = $this->getLinter();

    $config_options = $linter->getLinterConfigurationOptions();
    $this->assertTrue(isset($config_options['severity']));
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
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $proxied->expects()->shouldExpectCommandErrors()->andReturns(true);

    $this->assertTrue($linter->shouldExpectCommandErrors());
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
