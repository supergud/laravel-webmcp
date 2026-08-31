<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PHPStan bootstrap
|--------------------------------------------------------------------------
|
| Larastan's own bootstrap locates the application with
| getcwd() . '/bootstrap/app.php'. PHPStan's parallel worker processes do not
| always inherit the project root as their working directory, so in those
| workers the application is never booted and LARAVEL_VERSION is left
| undefined - which crashes LarastanStubFilesExtension.
|
| Pinning the working directory to the project root makes the analysis behave
| the same whether it runs in the main process or in a worker.
|
*/

chdir(__DIR__);

if (! defined('LARAVEL_VERSION')) {
    require __DIR__.'/vendor/larastan/larastan/bootstrap.php';
}
