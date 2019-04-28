<?php

final class ArcanistPHPStanLinterTestCase
  extends ArcanistExternalLinterTestCase {

  protected function getLinter(): ArcanistPHPStanLinter {
    $config_path = new TempFile('phpstan.neon');
    $config_content = <<<EOT
parameters:
  customRulesetUsed: true
  fileExtensions:
    - lint-test

rules:
  - PHPStan\Rules\Classes\InstantiationRule

EOT;

    Filesystem::writeFile(
      $config_path,
      $config_content);

    $linter = parent::getLinter();
    $linter->setLinterConfigurationValue('phpstan.config', (string)$config_path);

    return $linter;
  }

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/phpstan/');
  }
}
