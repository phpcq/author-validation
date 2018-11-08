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
     * @param GitRepository $git  The repository to extract all files from.
     *
     * @return array
     *
     * @throws \ReflectionException Which is not available on your PHP installation.
     * @throws \Psr\SimpleCache\InvalidArgumentException Thrown if the $key string is not a legal value.
     */
    private function getAuthorListFrom($path, GitRepository $git)
    {
        $relativePath = \substr($path, (\strlen($git->getRepositoryPath()) + 1));

        $commits = $this->fetchCommits($relativePath, $git);

        $authors = [];
        foreach ($commits as $commit) {
            if (isset($authors[\md5($commit['name'])])) {
                continue;
            }

            $authors[\md5($commit['name'])] = $commit;
        }

        return $authors;
    }

    /**
     * Fetch the commits.
     *
     * @param string        $filePath The file path.
     * @param GitRepository $git      The git repository.
     *
     * @return array
     *
     * @throws \ReflectionException Which is not available on your PHP installation.
     * @throws \Psr\SimpleCache\InvalidArgumentException Thrown if the $key string is not a legal value.
     */
    private function fetchCommits($filePath, GitRepository $git)
    {
        $logList = $this->fetchLogByFilePath($filePath, $git);
        if (!\count($logList)) {
            return [];
        }

        $commitList = $this->prepareCommitList($logList);
        $lastCommit = $this->getLastElementOfArray($commitList);

        $previousList = $this->fetchPreviousFromBlame($filePath, $lastCommit['commit'], $git);
        if (!\count($previousList)) {
            return $commitList;
        }

        $commitList = \array_merge($commitList, $this->walkingPathList($previousList, $git));

        return $commitList;
    }

    /**
     * Walking in the path list.
     *
     * @param array         $pathList The path list.
     * @param GitRepository $git      The git repository.
     *
     * @return array
     *
     * @throws \ReflectionException Which is not available on your PHP installation.
     * @throws \Psr\SimpleCache\InvalidArgumentException Thrown if the $key string is not a legal value.
     */
    private function walkingPathList(array $pathList, GitRepository $git)
    {
        $commitList = [];

        foreach ($pathList as $path) {
            $logList = $this->fetchLogByFilePath($path, $git);
            if (!\count($logList)) {
                continue;
            }

            $walkingCommitList = $this->prepareCommitList($logList);
            $commitList        = \array_merge($commitList, $walkingCommitList);
            $lastCommit        = $this->getLastElementOfArray($walkingCommitList);

            $previousList = $this->fetchPreviousFromBlame($path, $lastCommit['commit'], $git);
            if (\count($previousList)) {
                $commitList = \array_merge($commitList, $this->walkingPathList($previousList, $git));
            }

            $copyList = $this->fetchShowCommitWithFindCopies($path, $lastCommit['commit'], $git);
            if (\count($copyList)) {
                $commitList = \array_merge($commitList, $this->walkingPathList($copyList, $git));
            }
        }

        return $commitList;
    }

    /**
     * Fetch the log by file path.
     *
     * @param string        $filePath The file path.
     * @param GitRepository $git      The git repository.
     *
     * @return mixed
     *
     * @throws \ReflectionException Which is not available on your PHP installation.
     */
    private function fetchLogByFilePath($filePath, GitRepository $git)
    {
        $format = '{"commit": "%H", "name": "%aN", "email": "%ae", "subject": "%f", "date": "%ci", "date": "%ci"},';

        $arguments = [
            $git->getConfig()->getGitExecutablePath(),
            'log',
            '--simplify-merges',
            '--no-merges',
            '--format=' . $format,
            '--',
            $filePath
        ];

        return \json_decode(
            \sprintf(
                '[%s]',
                \trim(
                    // git log --simplify-merges --no-merges --format=$format -- $file
                    $this->runCustomGit($arguments, $git),
                    ','
                )
            ),
            true
        );
    }

    /**
     * Fetch the previous file list from the blame.
     *
     * @param string        $filePath The file path.
     * @param string        $commitId The commit identifier.
     * @param GitRepository $git      The git respository.
     *
     * @return array
     *
     * @throws \ReflectionException Which is not available on your PHP installation.
     */
    private function fetchPreviousFromBlame($filePath, $commitId, GitRepository $git)
    {
        $arguments = [
            $git->getConfig()->getGitExecutablePath(),
            'blame',
            $commitId,
            '--incremental',
            '--',
            $filePath
        ];

        // git blame $commitId --incremental -- $path
        $blame = $this->runCustomGit($arguments, $git);

        \preg_match_all('/(previous) (.+) (.+)/m', $blame, $match);
        if (!\count($match[3])) {
            return [];
        }

        return \array_unique($match[3]);
    }

    /**
     * Fetch a file path list from show with find copies.
     *
     * @param string        $filePath The file path.
     * @param string        $commitId The commit identifier.
     * @param GitRepository $git      The git repository.
     *
     * @return array
     *
     * @throws \ReflectionException Which is not available on your PHP installation.
     */
    private function fetchShowCommitWithFindCopies($filePath, $commitId, GitRepository $git)
    {
        $arguments = [
            $git->getConfig()->getGitExecutablePath(),
            'show',
            $commitId,
            '--find-copies'
        ];

        // git show $commitId --find-copies
        $show = $this->runCustomGit($arguments, $git);

        $renamingList = \array_unique($this->matchRenamingFromLog($show, $filePath));
        if (\in_array($filePath, $renamingList)) {
            $key = \array_search($filePath, $renamingList);
            unset($renamingList[$key]);
        }

        return $renamingList;
    }


    /**
     * Prepare the commit list.
     *
     * @param array $logList The log list.
     *
     * @return array
     */
    private function prepareCommitList(array $logList)
    {
        if (!\count($logList)) {
            return [];
        }

        $commitList = [];
        foreach ($logList as $log) {
            $commitList[$log['commit']] = $log;
        }

        return $commitList;
    }


    /**
     * Get the last element of a array.
     *
     * @param array $elementList The element list.
     *
     * @return array
     */
    private function getLastElementOfArray(array $elementList)
    {
        return \array_values(\array_slice($elementList, -1))[0];
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
        \preg_match_all('/^(rename|copy)\s+([^\n]*?)\n/m', $gitLog, $match);

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
     *
     * @throws \ReflectionException Which is not available on your PHP installation.
     * @throws \Psr\SimpleCache\InvalidArgumentException Thrown if the $key string is not a legal value.
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
