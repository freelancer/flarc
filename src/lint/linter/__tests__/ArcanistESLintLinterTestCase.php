<?php

final class ArcanistESLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  protected function getLinter(): ArcanistLinter {
    $config = new TempFile('eslintrc');

    // NOTE: We use `json_encode` rather than `phutil_json_encode` since we
    // need to set `$options` to include `JSON_FORCE_OBJECT`.
    Filesystem::writeFile(
      $config,
      json_encode([], JSON_FORCE_OBJECT));

    $linter = parent::getLinter();
    $linter->setLinterConfigurationValue('eslint.config', $config);

    return $linter;
  }

  public function testLinter(): void {
    $this->executeTestsInDirectory(__DIR__.'/eslint/');
  }

}
