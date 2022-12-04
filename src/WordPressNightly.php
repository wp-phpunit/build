<?php

namespace Build;

use Composer\Semver\VersionParser;
use PHPGit\Git;

/**
 * Class WordPressNightly
 *
 * @property-read string name
 */
class WordPressNightly extends WordPressGitTag
{
    /**
     * @return string
     */
    public function versionNormalized()
    {
        return "trunk";
    }

    public function isDotZero()
    {
        return false;
    }

    public function majorBranchName()
    {
        return "trunk";
    }
}
