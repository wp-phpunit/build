<?php

namespace Build;

use \Comodojo\Zip\Zip;
use Illuminate\Container\Container;
use PHPGit\Git;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

$paths = (object) [];
$paths->root = dirname(__DIR__);
$paths->build = "{$paths->root}/build";
$paths->working = "{$paths->root}/working";
$paths->template = "{$paths->root}/template";
$paths->repos = "{$paths->root}/repos";
$paths->wordpress = "{$paths->repos}/wordpress";
$paths->package = "{$paths->repos}/package";

require_once("{$paths->root}/vendor/autoload.php");

$container = new Container;
Container::setInstance($container);
$container->instance('paths', $paths);

$repos = (object) [];
$repos->wordpress = new Git;
$repos->wordpress->setRepository($paths->wordpress);
$repos->package = new Git;
$repos->package->setRepository($paths->package);
$container->instance('repos', $repos);

$package = json_decode(file_get_contents("{$paths->root}/template/composer.json"));
$container->instance('package', $package);
$build_version = $package->extra->{"wp-phpunit"}->version;

function init_branch($meta) {
    $repos = Container::getInstance()->get('repos');

    // 'empty' is the tagged empty root commit
    $repos->package->branch->create("tree-{$meta->tag}", 'empty');
}

function build($meta, $package_version = null) {
    $package_version = $package_version ?: $meta->package_version;
    $repos = Container::getInstance()->get('repos');
    $paths = Container::getInstance()->get('paths');
    $fs = new Filesystem;
    
    if ($fs->exists($paths->build)) {
        $fs->remove($paths->build);
    }

    $fs->mkdir("{$paths->build}/uncompressed/", 0755);
    $repos->wordpress->archive("{$paths->build}/source.zip", "{$meta->sha}:tests/phpunit", null, ['format' => 'zip']);
    Zip::open("{$paths->build}/source.zip")->extract("{$paths->build}/uncompressed/");

    $repos->package->checkout("tree-{$meta->tag}");
    $repos->package->reset->hard();

    collect((new Finder)->directories()->in("{$paths->build}/uncompressed/")->exclude(['build','tests']))->each(function($file) use ($fs, $paths) {
        $fs->copy($file->getRealPath(), $paths->package);
    });

    collect((new Finder)->files()->in($paths->template))->each(function($file) use ($fs, $paths) {
        $fs->copy($file->getRealPath(), $paths->package);
    });

    // $repos->package->add('.');
    $repos->package->commit("Building $package_version", ['all' => true]);
    $repos->package->tag->create($package_version);
    $repos->package->checkout('empty');
}

// Crawl tags
collect($repos->wordpress->tag())->filter(function($version) {
    // phpunit library was added in 3.7
    return version_compare($version, '3.7.0', '>=');
})->map(function($tag) use ($repos, $build_version) {
    $wp = $repos->wordpress;

    return (object) [
        'tag' => $tag,
        'sha' => trim(
            $wp->run($wp->getProcessBuilder()->add('rev-parse')->add($tag)->getProcess())
        ),
        'package_version' => $build_version ? "{$tag}-patch{$build_version}" : $tag,
    ];
})->sortBy('tag')->reject(function($meta) use ($repos) {
    // remove any which already exist on the target repository
    return collect($repos->package->tag())->contains($meta->package_version);
})->each(function($meta) use ($repos) { // leaves only packages to be built
    if (! collect($repos->package->branch())->keys()->contains("tree-{$meta->tag}")) {
        init_branch($meta);
    }

    build($meta);

    throw exception('stop here!');
});

