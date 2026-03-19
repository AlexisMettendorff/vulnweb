<?php

$getEnv = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
};

return [
    'database' => [
        'host' => $getEnv('APP_DB_HOST', 'devsecops-bdd'),
        'name' => $getEnv('APP_DB_NAME', 'myapp'),
        'user' => $getEnv('APP_DB_USER', 'appuser'),
        'password' => $getEnv('APP_DB_PASSWORD', 'app-password-dev'),
    ],
    'aws' => [
        'access_key_id' => $getEnv('AWS_ACCESS_KEY_ID'),
        'secret_access_key' => $getEnv('AWS_SECRET_ACCESS_KEY'),
    ],
];
