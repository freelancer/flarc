<?php

final class ArcanistPsalmLinterTestCase
  extends ArcanistExternalLinterTestCase {

  protected function getLinter() {
    $config_path = new TempFile('psalm.xml');
    $config_content = <<<EOT
<?xml version="1.0"?>
<psalm
    totallyTyped="false"
    resolveFromConfigFile="true"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="https://getpsalm.org/schema/config"
    xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd"
>
    <projectFiles>
        <directory name="." />
    </projectFiles>

    <issueHandlers>
        <LessSpecificReturnType errorLevel="info" />
    </issueHandlers>
</psalm>
EOT;

    Filesystem::writeFile(
      $config_path,
      $config_content);

    $linter = parent::getLinter();
    $linter->setLinterConfigurationValue(
      'psalm.config',
      (string)$config_path);

    return $linter;
  }

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/psalm/');
  }
}
