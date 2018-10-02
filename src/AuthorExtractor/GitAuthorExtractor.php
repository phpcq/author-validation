<?php

/**
 * This file is part of phpcq/author-validation.
 *
 * (c) 2014-2018 Christian Schiffler, Tristan Lins
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    phpcq/author-validation
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Tristan Lins <tristan@lins.io>
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2018 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use Bit3\GitPhp\GitRepository;
use PhpCodeQuality\AuthorValidation\AuthorExtractor;
use Symfony\Component\Finder\Finder;

/**
 * Extract the author information from a git repository.
 */
class GitAuthorExtractor implements AuthorExtractor
{
    use AuthorExtractorTrait;
    use GitAuthorExtractorTrait;

    /**
     * Optional attached finder for processing multiple files.
     *
     * @var Finder
     */
    protected $finder;

    /**
     * Convert the git binary output to a valid author list.
     *
     * @param array $authors The author list to convert.
     *
     * @return array
     */
    private function convertAuthorList(array $authors)
    {
        if (!$authors) {
            return [];
        }

        return \array_map(
            function ($author) {
                return $author['name'] . ' <' . $author['email'] . '>';
            },
            $authors
        );
    }

    /**
     * Check if the current file path is a file and if so, if it has staged modifications.
     *
     * @param string        $path A path obtained via a prior call to AuthorExtractor::getFilePaths().
     *
     * @param GitRepository $git  The repository to extract all files from.
     *
     * @return bool
     */
    private function isDirtyFile($path, $git)
    {
        if (!\is_file($path)) {
            return false;
        }

        $status  = $git->status()->short()->getIndexStatus();
        $relPath = \substr($path, (\strlen($git->getRepositoryPath()) + 1));

        if (isset($status[$relPath]) && $status[$relPath]) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve the author list from the given path via calling git.
     *
     * @param string        $path The path to check.
     *
     * @param GitRepository $git  The repository to extract all files from.
     *
     * @return array
     */
    private function getAuthorListFrom($path, $git)
    {
        $fileHistory = $this->renamingFileHistory($path, $git);

        $format = '{"commit": "%H", "name": "%aN", "email": "%ae", "subject": "%f", "date": "%ci"},';

        $authors = [];
        foreach ($fileHistory as $file) {
            $cacheId = \md5('authors/file/' . $file);
            if (!$this->cache->fetch($cacheId)) {
                $log = \json_decode(
                    '[' .
                    \trim(
                        // git log --format=$format --no-merges -- $file
                        $git->log()->follow()->format($format)->noMerges()->execute($file),
                        ','
                    )
                    . ']',
                    true
                );

                $data = [];
                foreach ($log as $commit) {
                    if (isset($authors[$commit['commit']])) {
                        continue;
                    }

                    // Sadly no command in our git library for this.
                    $arguments = [
                        $git->getConfig()->getGitExecutablePath(),
                        'show',
                        $commit['commit']
                    ];

                    $output = $this->runCustomGit($arguments, $git);
                    if (false === \strpos($output, $file)) {
                        continue;
                    }

                    $data[$commit['commit']] = $commit;
                }

                $this->cache->save($cacheId, \serialize($data));
            }

            $authors = \array_merge($authors, \unserialize($this->cache->fetch($cacheId)));
        }

        return $authors;
    }

    /**
     * Retrieve the history of given search for renaming.
     *
     * @param string        $path The file path.
     * @param GitRepository $git  The git repository.
     *
     * @return array
     */
    private function renamingFileHistory($path, GitRepository $git)
    {
        $relativePath = \substr($path, (\strlen($git->getRepositoryPath()) + 1));

        $mergeFileList    = $this->findRenamingFileByMerge($relativePath, $git);
        $renamingFileList = $this->filterRenamingByPathList([$relativePath], $git);
        $copyFileList     = $this->filterCopyByPathList(
            \array_unique(\array_merge($renamingFileList, $mergeFileList)),
            $git
        );

        $fileList = \array_unique(
            \array_merge([$relativePath], $renamingFileList, $mergeFileList, $copyFileList)
        );

        return $fileList;
    }

    /**
     * Filter the git log by renaming to in each file.
     *
     * @param array         $pathList The path list.
     * @param GitRepository $git      The git repository.
     *
     * @return array
     */
    private function filterRenamingByPathList(array $pathList, GitRepository $git)
    {
        $fileList = [];

        foreach ($pathList as $path) {
            // git log --follow -p -- $path
            $log = $git
                ->log()
                ->follow()
                ->revisionRange('-p')
                ->execute($path);

            if (false === \strpos($log, $path)) {
                continue;
            }

            preg_match_all('/^(rename)\s+([^\n]*?)\n/m', $log, $match);

            foreach ($match[2] as $row) {
                $fileList[] = \preg_replace('(^to |^from )', '', $row);
            }
        }

        return \array_unique($fileList);
    }

    /**
     * Find renaming file by merge commit.
     *
     * @param string        $relativePath The relative file path.
     * @param GitRepository $git          The git repository.
     *
     * @return array
     */
    private function findRenamingFileByMerge($relativePath, GitRepository $git)
    {
        $mergeCommit = $this->findMergeCommitByRelativePath($relativePath, $git);
        if (!\count($mergeCommit)) {
            return [$relativePath];
        }

        $fileList = [];
        foreach ($mergeCommit as $commit) {
            if (empty($commit['parent'])) {
                continue;
            }

            foreach (\explode(' ', $commit['parent']) as $parentCommitId) {
                // git diff --diff-filter=R PARENT_COMMIT_ID
                $arguments = [
                    $git->getConfig()->getGitExecutablePath(),
                    'diff',
                    '--diff-filter=R',
                    $parentCommitId
                ];

                $output = $this->runCustomGit($arguments, $git);
                if (false === \strpos($output, $relativePath)) {
                    continue;
                }

                foreach ($this->matchRenamingFromLog($output, $relativePath) as $path) {
                    $fileList[] = $path;
                }
            }
        }

        return $fileList;
    }

    /**
     * Filter the git log by copy to in each file.
     *
     * @param array         $pathList The path list.
     * @param GitRepository $git      The git repository.
     *
     * @return array
     */
    private function filterCopyByPathList(array $pathList, GitRepository $git)
    {
        $fileList = [];

        foreach ($pathList as $path) {
            $cacheId = \md5('log/follow/p/file/' . $path);
            if (!$this->cache->fetch($cacheId)) {
                // git log --follow -p -- $path
                $data = $git
                    ->log()
                    ->follow()
                    ->revisionRange('-p')
                    ->execute($path);

                $this->cache->save($cacheId, $data);
            }

            $log = $this->cache->fetch($cacheId);
            preg_match_all('/^(copy)\s+([^\n]*?)\n/m', $log, $match);

            foreach ($match[2] as $row) {
                $fileList[] = \preg_replace('(^to |^from )', '', $row);
            }
        }

        return \array_unique($fileList);
    }

    /**
     * Find merge commit by relative file path.
     *
     * @param string        $relativePath The relative file path.
     * @param GitRepository $git          The git repository.
     *
     * @return array
     */
    private function findMergeCommitByRelativePath($relativePath, GitRepository $git)
    {
        $format = '{"commit": "%H", "name": "%aN", "email": "%ae", "subject": "%f", "date": "%ci", "parent": "%P"},';

        return \json_decode(
            '[' .
            \trim(
                // git log --format=$format --merges -- $relativePath
                $git->log()->format($format)->merges()->execute($relativePath),
                ','
            )
            . ']',
            true
        );
    }

    /**
     * Match renaming file from the log.
     *
     * @param string $gitLog    The git log.
     * @param string $startFrom The relative path where start the search.
     *
     * @return array
     */
    private function matchRenamingFromLog($gitLog, $startFrom = '')
    {
        preg_match_all('/^(rename|copy)\s+([^\n]*?)\n/m', $gitLog, $match);

        $matchRenaming = [];
        foreach ($match[2] as $index => $row) {
            // Put the first renaming to the match list, find the first file by the start filter.
            if ($startFrom && (1 >= \count($matchRenaming))) {
                if ('to ' . $startFrom === $row) {
                    $fromRenaming = $match[2][($index - 1)];

                    $matchRenaming[\md5($fromRenaming)] = \preg_replace('(^from )', '', $fromRenaming);
                    $matchRenaming[\md5($row)]          = \preg_replace('(^to )', '', $row);
                }
                continue;
            }

            // Put the first renaming to the match list.
            if (1 >= \count($matchRenaming)) {
                $matchRenaming[\md5($row)] = \preg_replace('(^to |^from )', '', $row);

                continue;
            }

            \preg_match('(^to |^from )', $row, $renamingDirection);
            if ('from ' === $renamingDirection[0]) {
                continue;
            }

            $compareHash = \md5('from ' . \preg_replace('(^to )', '', $row));
            // If not found in the renaming file list, we are continue here.
            if (!\array_key_exists($compareHash, $matchRenaming)) {
                continue;
            }

            $fromRenaming = $match[2][($index - 1)];

            $matchRenaming[\md5($fromRenaming)] = \preg_replace('(^from )', '', $fromRenaming);
            $matchRenaming[\md5($row)]          = \preg_replace('(^to )', '', $row);
        }

        return $matchRenaming;
    }

    /**
     * Perform the extraction of authors.
     *
     * @param string $path A path obtained via a prior call to AuthorExtractor::getFilePaths().
     *
     * @return string[]|null
     */
    protected function doExtract($path)
    {
        $git = $this->getGitRepositoryFor($path);

        $authors = $this->convertAuthorList($this->getAuthorListFrom($path, $git));

        // Check if the file path is a file, if so, we need to check if it is "dirty" and someone is currently working
        // on it.
        if ($this->isDirtyFile($path, $git)) {
            $authors[] = $this->getCurrentUserInfo($git);
        }

        return $authors;
    }
}
