<?php

namespace Build;

use Comodojo\Zip\Zip;
use Illuminate\Container\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Initialize a new branch for the given package version.
 *
 * @param \stdClass $meta
 * @param string $starting
 *
 * @throws \Illuminate\Container\EntryNotFoundException
 */
function init_branch($meta, $starting = 'HEAD')
{
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
 * @throws \Comodojo\Exception\ZipException
 * @throws \Illuminate\Container\EntryNotFoundException
 */
function build($meta) {
	echo "\n\nBuilding $meta->tag\n";

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

	collect((new Finder)->directories()->in("{$paths->build}/uncompressed/")->depth(0)->exclude(['tests']))
		->each(function(SplFileInfo $dir) use ($fs, $paths) {
			echo "Mirroring directory ", $dir->getRelativePathname(), "\n";
			$fs->mirror($dir->getRealPath(), $paths->package . '/' . $dir->getRelativePathname());
		});

	collect((new Finder)->files()->in($paths->template))
		->each(function(SplFileInfo $file) use ($fs, $paths) {
			echo "Copying file ", $file->getRelativePathname(), "\n";
			$fs->copy($file->getRealPath(), $paths->package . '/' . $file->getRelativePathname());
		});

	$repos->package->add('.');

	// If we try to commit without any changes, a GitException will be thrown.
	if (0 < count($repos->package->status()['changes'])) {
		$repos->package->commit("Building $meta->tag", ['all' => true]);
	}

	$repos->package->tag->create($meta->tag);
}
