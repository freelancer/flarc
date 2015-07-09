<?php

/**
 * This class is intended to supplement the upstream `Filesystem` class.
 *
 * All of the code contained within this class is probably generic enough to be
 * submitted upstream at some stage, but for whatever reason it has not yet
 * been done.
 */
final class FlarcFilesystem extends Phobject {

  /**
   * Test whether a path is descendant from some root path.
   *
   * This is an improved version of `Filesystem::isDescendent` which works for
   * paths which do not exist on the filesystem.
   *
   * @param  string  Child path, absolute or relative to PWD.
   * @param  string  Root directory, absolute or relative to PWD.
   * @return bool    True if resolved child path is in fact a descendant of
   *                 resolved root path and both exist.
   * @task   path
   */
  public static function isDescendant($path, $root) {
    $path = Filesystem::resolvePath($path);
    $root = Filesystem::resolvePath($root);

    $relative_path = self::relativePath($root, $path);

    $parts = explode('/', $relative_path);
    return head($parts) != '..';
  }

  /**
   * Retrieve the relative location between two absolute paths.
   *
   * This function returns the relative path between two given absolute paths.
   * The implementation was based on a post on
   * [[http://stackoverflow.com/a/2638272/1369417 | StackOverflow]].
   *
   * @param  string  The source destination.
   * @param  string  The target destination.
   * @return string  The relative path between the source and target
   *                 destinations.
   * @task   path
   *
   * @todo Use `Filesystem::relativePath` after https://secure.phabricator.com/D13424.
   */
  public static function relativePath($from, $to) {
    // Some compatibility fixes for Windows paths.
    $from = is_dir($from) ? rtrim($from, '\/').'/' : $from;
    $to   = is_dir($to)   ? rtrim($to,   '\/').'/' : $to;
    $from = str_replace('\\', '/', $from);
    $to   = str_replace('\\', '/', $to);

    $from = explode('/', $from);
    $to   = explode('/', $to);

    $rel_path = $to;

    foreach ($from as $depth => $dir) {
      // Find first non-matching directory.
      if ($dir === $to[$depth]) {
        // Ignore this directory.
        array_shift($rel_path);
      } else {
        // Get number of remaining directories to $from.
        $remaining = count($from) - $depth;
        if ($remaining > 1) {
          // Add traversals up to first matching directory.
          $pad_length = (count($rel_path) + $remaining - 1) * -1;
          $rel_path = array_pad($rel_path, $pad_length, '..');
          break;
        }
      }
    }

    // `DIRECTORY_SEPARATOR` is not necessary. See
    // http://us2.php.net/manual/en/ref.filesystem.php#73954.
    return implode('/', $rel_path);
  }

  /**
   * Transpose a path from one directory onto another directory.
   *
   * This is useful for finding the corresponding files in directories with a
   * mirrored structure.
   *
   * It is expected that the path to be transposed is a descendent of the
   * specified source directory. If not, then a `FilesystemException` will be
   * thrown.
   *
   * @param  string  The path to be transposed.
   * @param  string  The source directory.
   * @param  string  The target directory.
   * @return string  The transposed directory.
   */
  public static function transposePath($path, $from, $to) {
    $relative_path = self::relativePath($from, $path);

    if (!self::isDescendant($path, $from)) {
      throw new FilesystemException(
        $path,
        pht("Path is not a descendant of '%s'.", $from));
    }

    if (!$relative_path) {
      return $to;
    }

    return rtrim($to, DIRECTORY_SEPARATOR).'/'.$relative_path;
  }

}
