<?php

final class ArcanistStylelintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  private $config;

  protected function getLinter() {
    $this->config = new TempFile();

    Filesystem::writeFile(
      $this->config,
      phutil_json_encode(
        [
          'rules' => array(
            'color-no-invalid-hex' => true,
          ),
        ]));

    $linter = parent::getLinter();
    $linter->setLinterConfigurationValue(
      'stylelint.config',
      (string)$this->config);

    return $linter;
  }

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/stylelint/');
  }
}
