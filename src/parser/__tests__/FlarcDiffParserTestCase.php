<?php

final class FlarcDiffParserTestCase extends PhutilTestCase {

  public function testUdiff() {
    $dir = __DIR__.'/udiffs/';
    $n_test = 0;
    foreach (Filesystem::listDirectory($dir) as $file) {
      if (preg_match('/\.diff-test$/', $file)) {
        $this->executeTest($file, $dir.$file);
        $n_test++;
      }
    }
  }

  private function executeTest($name, $file) {
    $contents = Filesystem::readFile($file);
    $contents = preg_split('/^~{4,}\n/m', $contents);

    if (count($contents) < 3) {
      throw new Exception(
        pht(
          "Expected '%s' separating test case and results (line, original text, changed text).",
          '~~~~~~~~~~'));
    }

    $data = $contents[0];
    $results = (new FlarcDiffParser())->parseDiff($data);
    for ($i = 3; $i < count($contents); $i += 3) {
        $expected_line = $contents[$i - 2];
        $expected_orig = $contents[$i - 1];
        $expected_new  = $contents[$i];

        $result = $results[(int)floor(($i - 1) / 3)];

        $this->assertEqual(
            (int)$expected_line,
            (int)$result->getLine(),
            pht('Test `%s` subsection `%s` failed', $name, 'line'));

        $this->assertEqual(
            $expected_orig,
            $result->getOriginal(),
            pht('Test `%s` subsection `%s` failed', $name, 'original text'));

        $this->assertEqual(
            $expected_new,
            $result->getReplacement(),
            pht('Test `%s` subsection `%s` failed', $name, 'changed text'));
    }


  }

}
