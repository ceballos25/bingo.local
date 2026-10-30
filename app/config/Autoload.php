<?php

spl_autoload_register(
    static function (string $class): void {
        $directories = [
            'config' => __DIR__,
            'entity' => dirname(__DIR__) . '/entity',
            'dto' => dirname(__DIR__) . '/dto',
            'repository' => dirname(__DIR__) . '/repository',
            'service' => dirname(__DIR__) . '/service',
            'controller' => dirname(__DIR__) . '/controller',
        ];

        foreach ($directories as $directory) {
            $file = $directory . '/' . $class . '.php';

            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
);
