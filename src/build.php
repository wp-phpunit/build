<?php

namespace Build;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPGit\Git;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = new Application();
$app->instance('path.root', dirname(__DIR__));
$app->instance('path.artifacts', "{$app['path.root']}/artifacts");
$app->instance('path.templates', "{$app['path.root']}/template");
$app->instance('path.repo.wordpress', "{$app['path.root']}/repos/wordpress");
$app->instance('path.repo.package', "{$app['path.root']}/repos/package");

$app->singleton('git.wordpress', function ($app) {
    return tap(new Git, function (Git $git) use ($app) {
        $git->setRepository($app['path.repo.wordpress']);
    });
});
$app->singleton('git.package', function ($app) {
    return tap(new Git, function (Git $git) use ($app) {
        $git->setRepository($app['path.repo.package']);
    });
});
$app->singleton('logger', function () {
    return tap(new Logger('build'), function (Logger $logger) {
        $logger->pushHandler(new StreamHandler(fopen('php://stdout', 'w')));
    });
});

$app->run();
