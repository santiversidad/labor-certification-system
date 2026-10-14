<?php

return [
    'driver' => env('HASH_DRIVER', 'argon2id'),
    'bcrypt' => ['rounds' => (int) env('BCRYPT_ROUNDS', 12), 'verify' => false, 'limit' => 128],
    // 19 MiB, 2 iterations, 1 lane: measured in CT before enabling.
    // verify=false permits progressive migration of existing bcrypt credentials.
    'argon' => ['memory' => (int) env('ARGON_MEMORY', 19456), 'threads' => 1, 'time' => (int) env('ARGON_TIME', 2), 'verify' => false],
    'rehash_on_login' => true,
];
