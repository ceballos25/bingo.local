<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/Env.php';
require_once __DIR__ . '/app/config/Autoload.php';

// Local: ~/websites/.env-bi · VPS CyberPanel: /home/bingo…/.env-bi (junto a bootstrap) o .env
$envCandidatos = [
    __DIR__ . '/.env-bi',
    __DIR__ . '/.env',
    dirname(__DIR__) . '/.env-bi',
    dirname(__DIR__) . '/.env',
];
$envPath = null;
foreach ($envCandidatos as $candidato) {
    if (is_file($candidato) && is_readable($candidato)) {
        $envPath = $candidato;
        break;
    }
}
if ($envPath === null) {
    throw new RuntimeException(
        'No se encontró .env-bi ni .env (raíz del proyecto o carpeta padre).'
    );
}
Env::load($envPath);
