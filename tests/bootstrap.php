<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap for the Adsbooster test suite.
 *
 * Application classes are loaded directly by the tests that need them
 * (they are not PSR-4 autoloadable). config/db.php defines the Database
 * class lazily — no connection is opened unless db() is called, so pure
 * logic tests never touch MySQL.
 */

require_once __DIR__ . '/../vendor/autoload.php';
