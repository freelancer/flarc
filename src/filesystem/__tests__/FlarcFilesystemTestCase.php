<?php

final class FlarcFilesystemTestCase extends PhutilTestCase {

  public function testIsDescendant() {
    $test_cases = array(
      array(
        '/foo/bar/baz',
        '/',
        true,
      ),
      array(
        '/foo/bar/baz',
        '/foo',
        true,
      ),
      array(
        '/foo/bar/baz',
        '/foo/bar/baz',
        true,
      ),
      array(
        '/foo/foobar',
        '/foo/bar/baz',
        false,
      ),
      array(
        '/',
        '/foo/bar/baz',
        false,
      ),
      array(
        'some/path',
        'src/',
        false,
      ),

      // Windows paths
      array(
        'c:\\Windows\\System32\\Drivers\\etc\\hosts',
        'c:\\',
        true,
      ),
      array(
        'c:\\Windows\\System32\\Drivers\\etc\\hosts',
        'c:\\Users\\Bill Gates\\',
        false,
      ),
    );

    foreach ($test_cases as $test_case) {
      list($path, $root, $expected) = $test_case;

      $this->assertEqual(
        $expected,
        FlarcFilesystem::isDescendant($path, $root),
        sprintf(
          'FlarcFilesystem::isDescendant(%s, %s)',
          phutil_var_export($path),
          phutil_var_export($root)));
    }
  }

  public function testRelativePath() {
    $test_cases = array(
      array(
        '/',
        '/foo/bar/baz',
        'foo/bar/baz',
      ),
      array(
        '/foo',
        '/foo/bar/baz',
        'bar/baz',
      ),
      array(
        '/foo/bar/baz',
        '/foo/foobar',
        '../foobar',
      ),

      // Windows paths
      array(
        'c:\\',
        'c:\\Windows\\System32\\Drivers\\etc\\hosts',
        'Windows/System32/Drivers/etc/hosts',
      ),
      array(
        'c:\\Users\\Bill Gates\\',
        'c:\\Windows\\System32\\Drivers\\etc\\hosts',
        '../../Windows/System32/Drivers/etc/hosts',
      ),
    );

    foreach ($test_cases as $test_case) {
      list($from, $to, $expected) = $test_case;

      $this->assertEqual(
        $expected,
        FlarcFilesystem::relativePath($from, $to),
        sprintf(
          'FlarcFilesystem::relativePath(%s, %s)',
          phutil_var_export($from),
          phutil_var_export($to)));
    }
  }

  public function testTransposePath() {
    $test_cases = array(
      array(
        '/foo/bar/baz',
        '/foo',
        '/bar',
        '/bar/bar/baz',
      ),
      array(
        '/foo/bar/baz',
        '/foo/bar/baz',
        '/bar',
        '/bar',
      ),
      array(
        'src/SomeClassTest.php',
        'src/',
        'test/',
        'test/SomeClassTest.php',
      ),
    );
    foreach ($test_cases as $test_case) {
      list($path, $from, $to, $expected) = $test_case;

      $this->assertEqual(
        $expected,
        FlarcFilesystem::transposePath($path, $from, $to),
        sprintf(
          'FlarcFilesystem::transposePath(%s, %s, %s)',
          phutil_var_export($path),
          phutil_var_export($from),
          phutil_var_export($to)));
    }

    $invalid_test_cases = array(
      array(
        '/foo/bar/baz',
        '/foobar/',
        '/bar',
      ),
    );
    foreach ($invalid_test_cases as $test_case) {
      list($path, $from, $to) = $test_case;

      $caught = null;
      try {
        FlarcFilesystem::transposePath($path, $from, $to);
      } catch (Exception $ex) {
        $caught = $ex;
      }

      $this->assertTrue(
        $caught instanceof FilesystemException,
        pht(
          'Expected `%s` to throw an exception.',
          sprintf('FlarcFilesystem::transposePath(%s, %s, %s)',
            phutil_var_export($path),
            phutil_var_export($from),
            phutil_var_export($to))));
    }
  }

}
