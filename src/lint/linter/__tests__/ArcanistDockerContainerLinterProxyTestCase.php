<?php

/**
 * @phutil-external-symbol class Mockery
 */
final class ArcanistDockerContainerLinterProxyTestCase
  extends ArcanistExternalLinterTestCase {


/* -(  Hooks  )-------------------------------------------------------------- */


  protected function willRunOneTest($test): void {
    putenv(ArcanistDockerContainerLinterProxy::ENV_SHOULD_PROXY);
  }

  protected function didRunOneTest($test): void {
    // Clean up the `Mockery` container used by the current test and run any
    // verification tasks needed for our expectations.
    Mockery::close();
  }


/* -(  Utility Methods  )---------------------------------------------------- */


  protected function getLintEngine(): ArcanistLintEngine {
    $working_copy = ArcanistWorkingCopyIdentity::newDummyWorkingCopy();

    $configuration_manager = new ArcanistConfigurationManager();
    $configuration_manager->setWorkingCopyIdentity($working_copy);

    $engine = new ArcanistUnitTestableLintEngine();
    $engine->setWorkingCopy($working_copy);
    $engine->setConfigurationManager($configuration_manager);

    return $engine;
  }

  protected function getLinter(): ArcanistLinter {
    $linter = new ArcanistDockerContainerLinterProxy();
    $linter->setShouldProxy(true);

    return $linter;
  }

  protected function getLinterWithMockProxiedLinter(): ArcanistLinter {
    $linter = new ArcanistDockerContainerLinterProxy();

    $engine = $this->getLintEngine();
    $linter->setEngine($engine);

    $mock = Mockery::mock(ArcanistExternalLinter::class);
    $mock->makePartial();
    $mock->shouldAllowMockingProtectedMethods();
    $linter->setProxiedLinter($mock);

    return $linter;
  }


/* -(  Tests  )-------------------------------------------------------------- */


  public function testClone(): void {
    $linter_a = $this->getLinterWithMockProxiedLinter();
    $linter_b = clone $linter_a;

    $proxied_linter_a = $linter_a->getProxiedLinter();
    $proxied_linter_b = $linter_b->getProxiedLinter();

    $this->assertFalse($proxied_linter_a === $proxied_linter_b);
  }

  public function testGetImage(): void {
    $linter = $this->getLinter();

    $image = 'ubuntu';
    $linter->setImage($image);

    $this->assertEqual($image, $linter->getImage());
  }

  public function testMount(): void {
    $linter = $this->getLinterWithMockProxiedLinter();

    $path  = __FILE__;
    $mount = sprintf('type=bind,source=%s,target=%s', $path, $path);

    $this->assertEqual($path, $linter->mount($path));
    $this->assertTrue(in_array($mount, $linter->getMounts()));
  }

  public function testGetProxiedLinter(): void {
    $linter  = $this->getLinter();
    $proxied = Mockery::mock(ArcanistExternalLinter::class);

    $engine = new ArcanistUnitTestableLintEngine();
    $linter->setEngine($engine);
    $linter->setProxiedLinter($proxied);

    $this->assertEqual($proxied, $linter->getProxiedLinter());
    $this->assertEqual($engine, $proxied->getEngine());
  }

  public function testGetProxiedLinterExecutableCommandWithoutInterpreter(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $binary = 'linter';
    $proxied->expects()->shouldUseInterpreter()->andReturns(false);
    $proxied->expects()->getDefaultBinary()->andReturns($binary);

    $this->assertEqual(
      (string)csprintf('%s', $binary),
      (string)$linter->getProxiedLinterExecutableCommand());
  }

  public function testGetProxiedLinterExecutableCommandWithInterpreter(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $interpreter = 'interpreter';
    $binary = 'linter';
    $proxied->expects()->shouldUseInterpreter()->andReturns(true);
    $proxied->expects()->getDefaultInterpreter()->andReturns($interpreter);
    $proxied->expects()->getDefaultBinary()->andReturns($binary);

    $this->assertEqual(
      (string)csprintf('%s %s', $interpreter, $binary),
      (string)$linter->getProxiedLinterExecutableCommand());
  }

  public function testGetProxiedLinterExecutableCommandWithNullInterpreter(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $binary = 'linter';
    $proxied->expects()->shouldUseInterpreter()->andReturns(true);
    $proxied->expects()->getDefaultInterpreter()->andReturns(null);
    $proxied->expects()->getDefaultBinary()->andReturns($binary);

    $this->assertEqual(
      (string)csprintf('%s', $binary),
      (string)$linter->getProxiedLinterExecutableCommand());
  }

  public function testShouldProxy(): void {
    $linter = $this->getLinterWithMockProxiedLinter();

    // Default behavior
    $this->assertTrue($linter->shouldProxy());

    $linter->setShouldProxy(true);
    $this->assertTrue($linter->shouldProxy());

    $linter->setShouldProxy(false);
    $this->assertFalse($linter->shouldProxy());
  }

  public function testShouldProxyFromEnvironment(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();

    $set_env = function (string $value): void {
      $key = ArcanistDockerContainerLinterProxy::ENV_SHOULD_PROXY;
      putenv($key.'='.$value);
    };

    $set_env('no');
    $this->assertFalse($linter->shouldProxy());

    $set_env('yes');
    $this->assertTrue($linter->shouldProxy());
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

    $code     = 1;
    $severity = ArcanistLintSeverity::SEVERITY_ADVICE;

    $linter->setCustomSeverityMap([$code => $severity]);
    $this->assertEqual($severity, $proxied->getLintMessageSeverity($code));
  }

  public function testAddCustomSeverityMap(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $code     = 1;
    $severity = ArcanistLintSeverity::SEVERITY_ADVICE;

    $linter->addCustomSeverityMap([$code => $severity]);
    $this->assertEqual($severity, $proxied->getLintMessageSeverity($code));
  }

  public function testSetCustomSeverityRules(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $code     = 1;
    $pattern  = pregsprintf('^%s$', '', $code);
    $severity = ArcanistLintSeverity::SEVERITY_ADVICE;

    $linter->setCustomSeverityRules([$pattern => $severity]);
    $this->assertEqual($severity, $proxied->getLintMessageSeverity($code));
  }

  public function testGetCacheVersion(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testCanRun(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $proxied->expects()->canRun()->andReturns(true);

    $this->assertTrue($linter->canRun());
  }

  public function testGetLinterName(): void {
    $linter = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $name = 'Linter';
    $proxied->expects()->getLinterName()->andReturns($name);

    $this->assertEqual($name, $linter->getLinterName());
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
    $linter = $this->getLinter();
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

  public function testShouldLintBinaryFiles(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $proxied->expects()->shouldLintBinaryFiles()->andReturns(true);

    $method = new ReflectionMethod($linter, 'shouldLintBinaryFiles');
    $method->setAccessible(true);

    $this->assertTrue($method->invoke($linter));
  }

  public function testShouldLintDeletedFiles(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $proxied->expects()->shouldLintDeletedFiles()->andReturns(true);

    $method = new ReflectionMethod($linter, 'shouldLintDeletedFiles');
    $method->setAccessible(true);

    $this->assertTrue($method->invoke($linter));
  }

  public function testShouldLintDirectories(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $proxied->expects()->shouldLintDirectories()->andReturns(true);

    $method = new ReflectionMethod($linter, 'shouldLintDirectories');
    $method->setAccessible(true);

    $this->assertTrue($method->invoke($linter));
  }

  public function testShouldLintSymbolicLinks(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $proxied->expects()->shouldLintSymbolicLinks()->andReturns(true);

    $method = new ReflectionMethod($linter, 'shouldLintSymbolicLinks');
    $method->setAccessible(true);

    $this->assertTrue($method->invoke($linter));
  }

  public function testGetLintCodeFromLinterConfigurationKey(): void {
    $this->assertSkipped(pht('Not yet implemented.'));
  }

  public function testDidResolveLinterFutures(): void {
    $linter  = $this->getLinterWithMockProxiedLinter();
    $proxied = $linter->getProxiedLinter();

    $futures = [new ExecFuture('true')];
    $proxied->expects()->didResolveLinterFutures($futures);

    $method = new ReflectionMethod($linter, 'didResolveLinterFutures');
    $method->setAccessible(true);
    $method->invoke($linter, $futures);

    $this->assertTrue(true);
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
    // On macOS, `$TMPDIR` points to a seemingly random path under `/var/folders`
    // (see http://osxdaily.com/2018/08/17/where-temp-folder-mac-access/).
    // According to the
    // [[https://docs.docker.com/docker-for-mac/osxfs/#namespaces | `osxfs`]]
    // documentation:
    //
    // > By default, you can share files in `/Users`, `/Volumes`, `/private`
    // > and `/tmp` directly. All other paths used in `-v` bind mounts are
    // > sourced from the Moby Linux VM running the Docker containers. If a
    // > macOS path is not shared and does not exist in the VM, an attempt to
    // > bind mount it fails rather than create it in the VM.
    if (PHP_OS === 'Darwin') {
      putenv('TMPDIR='.realpath('/tmp'));
    }

    $this->executeTestsInDirectory(__DIR__.'/docker-proxy/');
  }

}
