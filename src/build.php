<?php

namespace Build;

use Illuminate\Container\Container;
use PHPGit\Git;
use Composer\Semver\VersionParser;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container;

$paths = (object) [];
$paths->root = dirname(__DIR__);
$paths->build = "{$paths->root}/build";
$paths->template = "{$paths->root}/template";
$paths->repos = "{$paths->root}/repos";
$paths->wordpress = "{$paths->repos}/wordpress";
$paths->package = "{$paths->repos}/package";
$container->instance('paths', $paths);

$repos = (object)[];
$repos->wordpress = tap(new Git, function ($git) use ($paths) {
    $git->setRepository($paths->wordpress);
});
$repos->package = tap(new Git, function ($git) use ($paths) {
    $git->setRepository($paths->package);
});
$container->instance('repos', $repos);
$container->singleton('version_parser', VersionParser::class);
Container::setInstance($container);

$package = json_decode(file_get_contents("{$paths->root}/template/composer.json"));
$container->instance('package', $package);
$build_version = $package->extra->{"wp-phpunit"}->version;


// Crawl tags
collect($repos->wordpress->tag())->reject(function($tag) {
    return version_compare($tag, '3.7', '<'); // skip tags before phpunit library was added in 3.7
})->map(function($tag) use ($repos, $build_version) {
    $wp = $repos->wordpress;
    $package_version = $build_version ? "{$tag}-patch{$build_version}" : $tag;
    $version_parser = Container::getInstance()->make('version_parser');
    $major_version = collect(explode('.', $package_version))->take(2)->implode('.');

    return (object) [
        'tag' => $tag,
        'tag_normalized' => $version_parser->normalize($tag),
        'sha' => trim(
            $wp->run($wp->getProcessBuilder()->add('rev-parse')->add($tag)->getProcess())
        ),
        'major_version' => $major_version,
        'major_version_normalized' => $version_parser->normalize($major_version),
        'package_version' => $package_version,
        'package_version_normalized' => $version_parser->normalize($package_version),
    ];
})->sortBy('tag_normalized')->reject(function($meta) use ($repos) {
    return collect($repos->package->tag())->contains($meta->package_version); // remove any which already exist on the target repository
})->each(function($meta) use ($container, $repos) {
    $major_branch = "tree-{$meta->major_version}";
    $is_dot_zero = version_compare($meta->major_version_normalized, $meta->package_version_normalized, '=');
    $branch_exists = collect($repos->package->branch())->contains('name', $major_branch);

    if ($is_dot_zero && ! $branch_exists) {
        echo "\n\nInitializing branch for {$meta->major_version}\n";
        init_branch($meta, 'master');
        build($meta);

        try {
            $repos->package->checkout('master');
            $repos->package->merge($major_branch, null, ['no-ff' => true]);
        } catch(\Exception $e) {
            abort($e);
        }
    } else {
        build($meta);
    }
});

echo "\nBuild complete!\n";
