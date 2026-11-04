<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
if ($key === false || !openssl_pkey_export($key, $privateKey)) {
    throw new \RuntimeException('Cannot generate the ephemeral JWT test key.');
}
$details = openssl_pkey_get_details($key);
if ($details === false) {
    throw new \RuntimeException('Cannot extract the JWT test public key.');
}
$_SERVER['JWT_TEST_SECRET_KEY'] = $_ENV['JWT_TEST_SECRET_KEY'] = $privateKey;
$_SERVER['JWT_TEST_PUBLIC_KEY'] = $_ENV['JWT_TEST_PUBLIC_KEY'] = $details['key'];
unset($key, $privateKey, $details);

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
