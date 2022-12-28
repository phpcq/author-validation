<?php

/**
 * This file is part of phpcq/author-validation.
 *
 * (c) 2014-2022 Christian Schiffler, Tristan Lins
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    phpcq/author-validation
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2022 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use Bit3\GitPhp\GitRepository;
use PhpCodeQuality\AuthorValidation\AuthorExtractor;
use Symfony\Component\Finder\Finder;

use function array_map;
use function preg_replace;
use function preg_split;

/**
 * Extract the author information from a git repository. It does not care about which file where changed.
 */
class GitProjectAuthorExtractor implements AuthorExtractor
{
    use AuthorExtractorTrait;
    use GitAuthorExtractorTrait;

    /**
     * Optional attached finder for processing multiple files.
     *
     * @var Finder
     */
    protected Finder $finder;

    /**
     * Convert the git binary output to a valid author list.
     *
     * @param string $authors The author list to convert.
     *
     * @return string[]
     */
    private function convertAuthorList(string $authors): array
    {
        if (!$authors) {
            return array();
        }

        // remove commit sumary of author list
        return array_map(
            function ($author) {
                return preg_replace('~\s*([\d]+)\s+(.*)~', '$2', $author);
            },
            preg_split('~[\r\n]+~', $authors)
        );
    }

    /**
     * Check if git repository has uncommitted modifications.
     *
     * @param GitRepository $git The repository to extract all files from.
     *
     * @return bool
     */
    private function hasUncommittedChanges(GitRepository $git): bool
    {
        $status = $git->status()->short()->getIndexStatus();

        if (empty($status)) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve the author list from the git repository via calling git.
     *
     * @param GitRepository $git The repository to extract all files from.
     *
     * @return string
     */
    private function getAuthorListFrom(GitRepository $git): string
    {
        return $git->shortlog()->summary()->email()->revisionRange('HEAD')->execute();
    }

    /**
     * Perform the extraction of authors.
     *
     * @param string $path A path obtained via a prior call to AuthorExtractor::getFilePaths().
     *
     * @return string[]|null
     */
    protected function doExtract(string $path): ?array
    {
        $git = $this->getGitRepositoryFor($path);

        $authors = $this->convertAuthorList($this->getAuthorListFrom($git));

        // Check if repository has uncommitted changes, so that someone is currently working on it.
        if ($this->hasUncommittedChanges($git)) {
            $authors[] = $this->getCurrentUserInfo($git);
        }

        return $authors;
    }
}
