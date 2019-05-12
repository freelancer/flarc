<?php

final class ArcanistStylelintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  private $config;

  protected function getLinter(): ArcanistStylelintLinter {
    $this->config = new TempFile('stylelintrc');

    Filesystem::writeFile(
      $this->config,
      phutil_json_encode([
        'rules' => [
          'color-no-invalid-hex' => true,
        ],
      ]));

    $linter = parent::getLinter();
    $linter->setLinterConfigurationValue(
      'stylelint.config',
      (string)$this->config);

    return $linter;
  }

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/stylelint/');
  }

}
