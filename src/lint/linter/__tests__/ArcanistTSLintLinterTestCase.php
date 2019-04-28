<?php

final class ArcanistTSLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  private $config;

  protected function getLinter() {
    // We need to specify this configuration as newer versions of TSLint do not
    // enable any linter rules by default.
    $this->config = new TempFile('config.json');

    $config = '{ "rules": { "no-eval": true, "curly": true } }';

    Filesystem::writeFile($this->config, $config);

    $linter = parent::getLinter();
    $linter->setLinterConfigurationValue(
      'tslint.config',
      (string)$this->config);

    return $linter;
  }

  protected function executeTestsInDirectory($root) {
    $linter = $this->getLinter();

    $files = id(new FileFinder($root))
      ->withType('f')
      ->withSuffix('lint-test')
      ->find();

    $test_count = 0;
    foreach ($files as $file) {
      $this->lintFile($root.$file, $linter);
      $test_count++;
    }

    $this->assertTrue(
      ($test_count > 0),
      pht(
        'Expected to find some %s tests in directory %s!',
        '.lint-test',
        $root));
  }

  private function lintFile($file, ArcanistLinter $linter) {
    $linter = clone $linter;

    $contents = Filesystem::readFile($file);
    $contents = preg_split('/^~{4,}\n/m', $contents);
    if (count($contents) < 2) {
      throw new Exception(
        pht(
          "Expected '%s' separating test case and results.",
          '~~~~~~~~~~'));
    }

    list($data, $expect, $xform, $config) = array_merge(
      $contents,
      array(null, null));

    $basename = basename($file);

    if ($config) {
      $config = phutil_json_decode($config);
    } else {
      $config = array();
    }
    PhutilTypeSpec::checkMap(
      $config,
      array(
        'config' => 'optional map<string, wild>',
        'path' => 'optional string',
        'mode' => 'optional string',
        'stopped' => 'optional bool',
      ));

    $exception = null;
    $after_lint = null;
    $messages = null;
    $exception_message = false;
    $caught_exception = false;

    try {
      $tmp = new TempFile(pathinfo($basename, PATHINFO_FILENAME));
      Filesystem::writeFile($tmp, $data);
      $full_path = (string)$tmp;

      $mode = idx($config, 'mode');
      if ($mode) {
        Filesystem::changePermissions($tmp, octdec($mode));
      }

      $dir = dirname($full_path);
      $path = basename($full_path);

      $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
        $dir,
        null,
        pht('Unit Test'));
      $configuration_manager = new ArcanistConfigurationManager();
      $configuration_manager->setWorkingCopyIdentity($working_copy);


      $engine = new ArcanistUnitTestableLintEngine();
      $engine->setWorkingCopy($working_copy);
      $engine->setConfigurationManager($configuration_manager);

      $path_name = idx($config, 'path', $path);
      $engine->setPaths(array($path_name));

      $linter->addPath($path_name);
      $linter->addData($path_name, $data);

      foreach (idx($config, 'config', array()) as $key => $value) {
        $linter->setLinterConfigurationValue($key, $value);
      }

      $engine->addLinter($linter);
      $engine->addFileData($path_name, $data);

      $results = $engine->run();
      $this->assertEqual(
        1,
        count($results),
        pht('Expect one result returned by linter.'));

      $assert_stopped = idx($config, 'stopped');
      if ($assert_stopped !== null) {
        $this->assertEqual(
          $assert_stopped,
          $linter->didStopAllLinters(),
          $assert_stopped
            ? pht('Expect linter to be stopped.')
            : pht('Expect linter to not be stopped.'));
      }

      $result = reset($results);
      $patcher = ArcanistLintPatcher::newFromArcanistLintResult($result);
      $after_lint = $patcher->getModifiedFileContent();
    } catch (PhutilTestTerminatedException $ex) {
      throw $ex;
    } catch (Exception $exception) {
      $caught_exception = true;
      if ($exception instanceof PhutilAggregateException) {
        $caught_exception = false;
        foreach ($exception->getExceptions() as $ex) {
          if ($ex instanceof ArcanistUsageException ||
              $ex instanceof ArcanistMissingLinterException) {
            $this->assertSkipped($ex->getMessage());
          } else {
            $caught_exception = true;
          }
        }
      } else if ($exception instanceof ArcanistUsageException ||
                 $exception instanceof ArcanistMissingLinterException) {
        $this->assertSkipped($exception->getMessage());
      }
      $exception_message = $exception->getMessage()."\n\n".
                           $exception->getTraceAsString();
    }

    $compare_lint_method = new ReflectionMethod($this, 'compareLint');
    $compare_lint_method->setAccessible(true);
    $compare_transform_method = new ReflectionMethod($this, 'compareTransform');
    $compare_transform_method->setAccessible(true);

    $this->assertEqual(false, $caught_exception, $exception_message);

    // FIXME: Access to `$result` is unsafe
    $compare_lint_method->invoke($this, $basename, $expect, $result);
    $compare_transform_method->invoke($this, $xform, $after_lint);
  }


  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/tslint/');
  }

}
