<?php

final class ArcanistPHPCSFixerLinterTestCase
  extends ArcanistExternalLinterTestCase {

  private $config;

  protected function getLinter() {
    $this->config = new TempFile();
    $config_data = <<<EOT
<?php

return PhpCsFixer\Config::create()
    ->setRules([
        'braces' => true,
    ]);
EOT;

    Filesystem::writeFile(
      $this->config,
      $config_data);

    $linter = parent::getLinter();
    $linter->setLinterConfigurationValue(
      'php-cs-fixer.config',
      (string)$this->config);

    return $linter;
  }

  public function testLinter() {
    $this->executeTestsInDirectory(__DIR__.'/php_cs_fixer/');
  }

}
