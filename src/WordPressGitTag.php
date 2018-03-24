<?php

namespace Build;

use Composer\Semver\VersionParser;
use PHPGit\Git;

/**
 * Class WordPressGitTag
 *
 * @property-read string name
 * @property-read string majorVersion
 */
class WordPressGitTag
{
    protected $majorVersion;
    private $name;
    private $git;
    private $versionParser;

    /**
     * WordPressGitTag constructor.
     * @param string $name
     * @param Git $git
     */
    public function __construct($name, $git)
    {
        $this->name = $name;
        $this->git = $git;
        $this->versionParser = new VersionParser;
        $this->majorVersion = collect(explode('.', $name))->take(2)->implode('.');
    }

    /**
     * @return string
     */
    public function versionNormalized()
    {
        return $this->versionParser->normalize($this->name);
    }

    public function isDotZero()
    {
        return version_compare(
            $this->versionParser->normalize($this->majorVersion),
            $this->versionNormalized(),
            '='
        );
    }

    public function majorBranchName()
    {
        return "tree-{$this->majorVersion}";
    }

    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }

        throw new \Exception("No property [$name] exists");
    }

    public function sha()
    {
        return trim($this->git->run(
            $this->git->getProcessBuilder()->add('rev-parse')->add($this->name)->getProcess()
        ));
    }

}