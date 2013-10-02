<?php
namespace Tests;

// Common path definitions
define('ROOT_PATH', realpath(__DIR__.'/..'));

// Everything is relative to the project root now.
chdir(ROOT_PATH);

// Set up autoloading (after composer install)
require_once ROOT_PATH . '/vendor/autoload.php';