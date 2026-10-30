<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/Env.php';
require_once __DIR__ . '/app/config/Autoload.php';

Env::load(dirname(__DIR__) . '/.env-bi');
