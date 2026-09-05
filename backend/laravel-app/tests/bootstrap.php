<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;

require dirname(__DIR__).'/vendor/autoload.php';

// Never let RefreshDatabase invoke destructive migrations, even in the test suite.
// Prepare the existing *_test database with `php artisan migrate` before running tests.
if (! str_ends_with((string) getenv('DB_DATABASE'), '_test')) {
    throw new RuntimeException('Tests require an explicitly configured *_test database.');
}
RefreshDatabaseState::$migrated = true;
