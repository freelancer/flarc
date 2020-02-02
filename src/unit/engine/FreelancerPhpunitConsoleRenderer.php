<?php

final class FreelancerPhpunitConsoleRenderer extends ArcanistUnitRenderer {

  public function getName(
    ?ArcanistConfigurationManager $configuration_manager): string {
    $result_array = $configuration_manager->getConfigFromAllSources('name');
    $name = $result_array['runtime'];

    return phutil_console_format('<bg:green>** %s **</bg>', $name);
  }

  public function printFailingUnitTests(array $errors): void {
    if ($errors) {
      echo phutil_console_format(
        '<bg:red>** %s **</bg>',
        pht('Failed Test Results:'));
      echo "\n";
    }

    foreach ($errors as $error) {
      echo $this->renderer->renderUnitResult($error);
    }
  }

  public function renderBasicResult(ArcanistUnitTestResult $result): string {
    $result_code = $result->getResult();

    switch ($result_code) {
      case ArcanistUnitTestResult::RESULT_PASS:
        return '.';

      case ArcanistUnitTestResult::RESULT_FAIL:
        return 'F';

      case ArcanistUnitTestResult::RESULT_SKIP:
        return 'S';

      case ArcanistUnitTestResult::RESULT_BROKEN:
        return 'E';

      case ArcanistUnitTestResult::RESULT_UNSOUND:
        return 'U';

      default:
        return '';
    }
  }

  public function renderUnitResult(ArcanistUnitTestResult $result) {
    $result_code = $result->getResult();

    $duration = '';
    if ($result_code == ArcanistUnitTestResult::RESULT_PASS) {
      $duration = ' '.$this->formatTestDuration($result->getDuration());
    }

    $test_name = $result->getName();
    $test_namespace = $result->getNamespace();
    if (strlen($test_namespace)) {
      $test_name = $test_namespace.'::'.$test_name;
    }

    $return = sprintf(
      "  %s %s\n",
      $this->getFormattedResult($result->getResult()).$duration,
      $test_name);

    if ($result_code != ArcanistUnitTestResult::RESULT_PASS
        && strlen($result->getUserData())) {
      $return .= $result->getUserData()."\n";
    }

    return $return;
  }

  private function getFormattedResult($result) {
    switch ($result) {
      case ArcanistUnitTestResult::RESULT_PASS:
        return phutil_console_format('<bg:green>** %s **</bg>', pht('PASS'));

      case ArcanistUnitTestResult::RESULT_FAIL:
        return phutil_console_format('<bg:red>** %s **</bg>', pht('FAIL'));

      case ArcanistUnitTestResult::RESULT_SKIP:
        return phutil_console_format('<bg:yellow>** %s **</bg>', pht('SKIP'));

      case ArcanistUnitTestResult::RESULT_BROKEN:
        return phutil_console_format('<bg:red>** %s **</bg>', pht('BROKEN'));

      case ArcanistUnitTestResult::RESULT_UNSOUND:
        return phutil_console_format(
          '<bg:yellow>** %s **</bg>',
          pht('UNSOUND'));

      default:
        return null;
    }
  }

  private function formatTestDuration($seconds) {
    // Very carefully define inclusive upper bounds on acceptable unit test
    // durations. Times are in milliseconds and are in increasing order.
    $star = "\xE2\x98\x85";
    if (phutil_is_windows()) {
      // Fall-back to normal asterisk for Windows consoles.
      $star = '*';
    }
    $acceptableness = array(
      50   => "<fg:green>%s</fg><fg:yellow>{$star}</fg> ",
      200  => '<fg:green>%s</fg>  ',
      500  => '<fg:yellow>%s</fg>  ',
      INF  => '<fg:red>%s</fg>  ',
    );

    $milliseconds = $seconds * 1000;
    $duration = $this->formatTime($seconds);
    foreach ($acceptableness as $upper_bound => $formatting) {
      if ($milliseconds <= $upper_bound) {
        return phutil_console_format($formatting, $duration);
      }
    }
    return phutil_console_format(end($acceptableness), $duration);
  }

  private function formatTime($seconds) {
    if ($seconds >= 60) {
      $minutes = floor($seconds / 60);
      return pht('%dm%02ds', $minutes, round($seconds % 60));
    }

    if ($seconds >= 1) {
      return pht('%4.1fs', $seconds);
    }

    $milliseconds = $seconds * 1000;
    if ($milliseconds >= 1) {
      return pht('%3dms', round($milliseconds));
    }

    return pht(' <%dms', 1);
  }

}
