<?php

namespace Build;

use \Comodojo\Zip\Zip;
use Illuminate\Container\Container;
use PHPGit\Git;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
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

$container->instance('repos', (object) [
    'wordpress' => tap(new Git, function ($git) use ($paths) {
        $git->setRepository($paths->wordpress);
    }),
    'package' => tap(new Git, function ($git) use ($paths) {
        $git->setRepository($paths->package);
    }),
]);
$container->singleton('version_parser', VersionParser::class);
Container::setInstance($container);

$package = json_decode(file_get_contents("{$paths->root}/template/composer.json"));
$container->instance('package', $package);
$build_version = $package->extra->{"wp-phpunit"}->version;

/**
 * Initialize a new branch for the given package version.
 *
 * @param \stdClass $meta
 * @param string $starting
 */
function init_branch($meta, $starting = 'HEAD') {
    Container::getInstance()
        ->get('repos')
        ->package
        ->branch
        ->create("tree-{$meta->major_version}", $starting)
    ;
}

/**
 * Build the package for the given version.
 *
 * @param \stdClass $meta
 * @param string $package_version
 */
function build($meta, $package_version = null) {
    echo "\n\nBuilding $meta->package_version\n";

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

    $repos->package->checkout("tree-{$meta->major_version}");
    $repos->package->reset->hard();
    $repos->package->run($repos->package->getProcessBuilder()->add('clean')->add('-df')->getProcess());

    collect((new Finder)->directories()->in("{$paths->build}/uncompressed/")->depth(0)->exclude(['build','tests']))->each(function($dir) use ($fs, $paths) {
        echo "Mirroring directory ", $dir->getRelativePathname(), "\n";
        $fs->mirror($dir->getRealPath(), $paths->package . '/' . $dir->getRelativePathname());
    });

    collect((new Finder)->files()->in($paths->template))->each(function($file) use ($fs, $paths) {
        echo "Copying file ", $file->getRelativePathname(), "\n";
        $fs->copy($file->getRealPath(), $paths->package . '/' . $file->getRelativePathname());
    });

    try {
        $repos->package->add('.');
        
        // If we try to commit without any changes, a GitException will be thrown.
        if (0 < count($repos->package->status()['changes'])) {
            $repos->package->commit("Building $package_version", ['all' => true]);
        }

        $repos->package->tag->create($package_version);
    } catch (\Exception $e) {
        var_dump($e);
        exit('1');
    }
}

// Crawl tags
collect($repos->wordpress->tag())->reject(function($version) {
    // phpunit library was added in 3.7
    return version_compare($version, '3.7', '<');
})->map(function($tag) use ($repos, $build_version) {
    $wp = $repos->wordpress;
    $package_version = $build_version ? "{$tag}-patch{$build_version}" : $tag;
    $version_parser = Container::getInstance()->make('version_parser');

    return (object) [
        'tag' => $tag,
        'tag_normalized' => $version_parser->normalize($tag),
        'sha' => trim(
            $wp->run($wp->getProcessBuilder()->add('rev-parse')->add($tag)->getProcess())
        ),
        'major_version' => collect(explode('.', $package_version))->take(2)->implode('.'),
        'package_version' => $package_version,
        'version_normalized' => $version_parser->normalize($package_version),
    ];
})->sortBy('tag_normalized')->reject(function($meta) use ($repos) {
    // remove any which already exists on the target repository
    return collect($repos->package->tag())->contains($meta->package_version);
})->each(function($meta) use ($container, $repos) { // leaves only packages to be built    
    $major_normalized = $container['version_parser']->normalize($meta->major_version);
    
    // Initialize a new "tree-{major-version}" branch if we're starting a tag for a .0 release
    if (version_compare($major_normalized, $meta->version_normalized, '=')) {
        echo "\n\nInitializing branch for {$meta->major_version}\n";
        
        init_branch($meta);
    }

    build($meta);
});

echo "\nBuild complete!\n";
