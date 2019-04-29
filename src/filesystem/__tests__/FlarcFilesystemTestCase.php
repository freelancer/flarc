<?php

final class FlarcFilesystemTestCase extends PhutilTestCase {

  public function testIsDescendant() {
    $test_cases = [
      [
        '/foo/bar/baz',
        '/',
        true,
      ],
      [
        '/foo/bar/baz',
        '/foo',
        true,
      ],
      [
        '/foo/bar/baz',
        '/foo/bar/baz',
        true,
      ],
      [
        '/foo/foobar',
        '/foo/bar/baz',
        false,
      ],
      [
        '/',
        '/foo/bar/baz',
        false,
      ],
      [
        'some/path',
        'src/',
        false,
      ],

      // Windows paths
      [
        'c:\\Windows\\System32\\Drivers\\etc\\hosts',
        'c:\\',
        true,
      ],
      [
        'c:\\Windows\\System32\\Drivers\\etc\\hosts',
        'c:\\Users\\Bill Gates\\',
        false,
      ],
    ];

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
    $test_cases = [
      [
        '/',
        '/foo/bar/baz',
        'foo/bar/baz',
      ],
      [
        '/foo',
        '/foo/bar/baz',
        'bar/baz',
      ],
      [
        '/foo/bar/baz',
        '/foo/foobar',
        '../foobar',
      ],

      // Windows paths
      [
        'c:\\',
        'c:\\Windows\\System32\\Drivers\\etc\\hosts',
        'Windows/System32/Drivers/etc/hosts',
      ],
      [
        'c:\\Users\\Bill Gates\\',
        'c:\\Windows\\System32\\Drivers\\etc\\hosts',
        '../../Windows/System32/Drivers/etc/hosts',
      ],
    ];

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
    $test_cases = [
      [
        '/foo/bar/baz',
        '/foo',
        '/bar',
        '/bar/bar/baz',
      ],
      [
        '/foo/bar/baz',
        '/foo/bar/baz',
        '/bar',
        '/bar',
      ],
      [
        'src/SomeClassTest.php',
        'src/',
        'test/',
        'test/SomeClassTest.php',
      ],
    ];
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

    $invalid_test_cases = [
      [
        '/foo/bar/baz',
        '/foobar/',
        '/bar',
      ],
    ];
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
