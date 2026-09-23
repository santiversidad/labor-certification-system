<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;

require dirname(__DIR__).'/vendor/autoload.php';

// Docker injects CACHE_STORE=file before PHPUnit reads phpunit.xml. Override it
// before Laravel boots so test role IDs never reach the application's file cache.
putenv('CACHE_STORE=array');
$_ENV['CACHE_STORE'] = 'array';
$_SERVER['CACHE_STORE'] = 'array';

// Never let RefreshDatabase invoke destructive migrations, even in the test suite.
// Prepare the existing *_test database with `php artisan migrate` before running tests.
if (! str_ends_with((string) getenv('DB_DATABASE'), '_test')) {
    throw new RuntimeException('Tests require an explicitly configured *_test database.');
}
RefreshDatabaseState::$migrated = true;
