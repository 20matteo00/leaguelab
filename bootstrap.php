<?php

spl_autoload_register(function ($class) {

    $paths = [
        __DIR__ . '/helper/' . $class . '.php'
    ];

    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
