<?php

final class ArcanistESLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  private $eslintconfig;

  protected function getLinter() {
    // We need to specify this configuration as newer versions of ESLint do not
    // enable any linter rules by default.
    $this->eslintconfig = new TempFile();

    Filesystem::writeFile(
      $this->eslintconfig,
      phutil_json_encode(
        array(
          'extends' => 'eslint:recommended',
        )));

    $linter = parent::getLinter();
    $linter->setLinterConfigurationValue(
      'eslint.config',
      (string)$this->eslintconfig);

    return $linter;
  }

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/eslint/');
  }

}
