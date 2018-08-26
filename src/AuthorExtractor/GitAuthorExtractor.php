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

use Bit3\GitPhp\GitException;
use Bit3\GitPhp\GitRepository;
use PhpCodeQuality\AuthorValidation\AuthorExtractor;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

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
     *
     * @throws GitException Throws an exception if the git command can not execute.
     */
    private function getAuthorListFrom($path, $git)
    {
        $fileHistory = $this->renamingFileHistory($path, $git);

        $format = '{"commit": "%H", "name": "%aN", "email": "%ae", "subject": "%f", "date": "%ci"},';

        $authors = [];

        foreach ($fileHistory as $file) {
            $log = \json_decode(
                '[' .
                \trim(
                    // git log --format=$format --no-merges
                    $git->log()->follow()->format($format)->noMerges()->execute($file),
                    ','
                )
                . ']',
                true
            );

            foreach ($log as $commit) {
                if (isset($authors[$commit['commit']])) {
                    continue;
                }

                // Sadly no command in our git library for this.
                $arguments = [
                    $git->getConfig()->getGitExecutablePath(),
                    'show',
                    $commit['commit'],
                    '--',
                    $file
                ];

                $process = new Process($this->prepareProcessArguments($arguments), $git->getRepositoryPath());
                $git->getConfig()->getLogger()->debug(
                    \sprintf('[git-php] exec [%s] %s', $process->getWorkingDirectory(), $process->getCommandLine())
                );

                $process->run();
                $output = \rtrim($process->getOutput(), "\r\n");

                if (!$process->isSuccessful()) {
                    throw GitException::createFromProcess('Could not execute git command', $process);
                }

                if (!$output) {
                    continue;
                }

                $authors[$commit['commit']] = $commit;
            }
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
     *
     * @throws GitException Throws an exception if the git command can not execute.
     */
    private function renamingFileHistory($path, GitRepository $git)
    {
        // Sadly no command in our git library for this.
        $arguments = [
            $git->getConfig()->getGitExecutablePath(),
            'log',
            '--follow',
            '--diff-filter=R',
            '-p',
            '--',
            $path
        ];

        $process = new Process($this->prepareProcessArguments($arguments), $git->getRepositoryPath());
        $git->getConfig()->getLogger()->debug(
            sprintf('[git-php] exec [%s] %s', $process->getWorkingDirectory(), $process->getCommandLine())
        );

        $process->run();
        $output = rtrim($process->getOutput(), "\r\n") . "\n";

        $relativePath = \substr($path, (\strlen($git->getRepositoryPath()) + 1));
        if (false === \strpos($output, $relativePath)) {
            return [$relativePath];
        }

        if (!$process->isSuccessful()) {
            throw GitException::createFromProcess('Could not execute git command', $process);
        }

        \preg_match_all('/rename(.*?)\n/', $output, $match);

        return \array_map(
            function ($row) {
                return \preg_replace('( to | from )', '', $row);
            },
            $match[1]
        );
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
