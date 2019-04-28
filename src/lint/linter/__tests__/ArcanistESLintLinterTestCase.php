<?php

final class ArcanistESLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  private $config;

  protected function getLinter() {
    // We need to specify this configuration as newer versions of ESLint do not
    // enable any linter rules by default.
    $this->config = new TempFile();

    Filesystem::writeFile(
      $this->config,
      phutil_json_encode(
        [
          'plugins' => ['prettier'],
        ]));

    $linter = parent::getLinter();
    $linter->setLinterConfigurationValue(
      'eslint.config',
      (string)$this->config);

    return $linter;
  }

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/eslint/');
  }

}
