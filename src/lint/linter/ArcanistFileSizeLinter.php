<?php

/**
 * Stop developers from uploading file sizes greater than 10MB
 */
final class ArcanistFileSizeLinter extends ArcanistLinter {

  const LINT_BAD_FILESIZE = 1;
  const FILE_SIZE_LIMIT = 10485760;
  const ASSETS_UPLOADER_DOCUMENT = 'https://phabricator.tools.flnltd.com/w/gaf/tools/uploadassets/';

  public function getInfoName() {
    return pht('FileSize');
  }

  public function getInfoDescription() {
    return pht(
      'Requiring files to be less than 10MB ');
  }

  public function getLinterName() {
    return 'FileSize';
  }

  public function getLinterConfigurationName() {
    return 'filesize';
  }

  public function getLintNameMap() {
    return array(
      self::LINT_BAD_FILESIZE => pht('Bad Filesize'),
    );
  }

  public function lintPath($path) {
    $size = filesize($path);
    $size_string = $this->bytesToHumanReadableString($size);
    if ($size > self::FILE_SIZE_LIMIT) {
      $this->raiseLintAtPath(
        self::LINT_BAD_FILESIZE,
        pht(
          "%s is %s\n\n".
          "Files over 10MB should not be committed.\n".
          "Please use the assets uploader service for storing files ".
          "over 10MB\n%s\n",
          $path,
          $size_string,
          self::ASSETS_UPLOADER_DOCUMENT));
    }
  }

  protected function shouldLintBinaryFiles() {
    return true;
  }

  protected function shouldLintDirectories() {
    return false;
  }
  protected function shouldLintSymbolicLinks() {
    return false;
  }

  private function bytesToHumanReadableString($size, $precision = 2) {
    for ($i = 0; ($size / 1024) > 0.9; $i++, $size /= 1024) {}
    return round($size, $precision).
      ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'][$i];
}

}
