<?php

$moduleRoot = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($moduleRoot): void {
    $prefix = 'Modules\\Glitter\\FormBuilder\\';
    if (! str_starts_with($class, $prefix)) return;
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = $moduleRoot.'/src/'.$relative.'.php';
    if (is_file($path)) require_once $path;
});
