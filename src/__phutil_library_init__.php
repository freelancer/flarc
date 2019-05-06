<?php

phutil_register_library('flarc', __FILE__);

// Register the Composer autoloader.
//
// NOTE: Composer dependencies are currently only required for unit testing.
if (Filesystem::pathExists(__DIR__.'/../vendor/autoload.php')) {
  include_once __DIR__.'/../vendor/autoload.php';
}
