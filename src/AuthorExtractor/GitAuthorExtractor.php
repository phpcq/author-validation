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
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Extract the author information from a git repository.
 */
class GitAuthorExtractor implements AuthorExtractor
{
    use AuthorExtractorTrait;

    /**
     * Optional attached finder for processing multiple files.
     *
     * @var Finder
     */
    protected $finder;

    /**
     * Create a git repository instance.
     *
     * @param string $path A path within a git repository.
     *
     * @return GitRepository.
     */
    private function getGitRepositoryFor($path)
    {
        $git = new GitRepository($this->determineGitRoot($path));
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $git->getConfig()->setLogger(
                new ConsoleLogger($this->output)
            );
        }

        return $git;
    }

    /**
     * Determine the git root, starting from arbitrary directory.
     *
     * @param string $path The start path.
     *
     * @return string The git root path.
     *
     * @throws \RuntimeException If the git root could not determined.
     */
    private function determineGitRoot($path)
    {
        // @codingStandardsIgnoreStart
        while (\strlen($path) > 1) {
            // @codingStandardsIgnoreEnd
            if (\is_dir($path . DIRECTORY_SEPARATOR . '.git')) {
                return $path;
            }

            $path = \dirname($path);
        }

        throw new \RuntimeException('Could not determine git root, starting from ' . \func_get_arg(0));
    }

    /**
     * Retrieve a list of all files within a git repository.
     *
     * @param GitRepository $git The repository to extract all files from.
     *
     * @return string[]
     *
     * @throws GitException When the git execution failed.
     */
    private function getAllFilesFromGit($git)
    {
        // Sadly no command in our git library for this.
        $arguments = [
            $git->getConfig()->getGitExecutablePath(),
            'ls-tree',
            'HEAD',
            '-r',
            '--full-name',
            '--name-only'
        ];

        $process = new Process($arguments, $git->getRepositoryPath());

        $git->getConfig()->getLogger()->debug(
            \sprintf('[git-php] exec [%s] %s', $process->getWorkingDirectory(), $process->getCommandLine())
        );

        $process->run();
        $output = \rtrim($process->getOutput(), "\r\n");

        if (!$process->isSuccessful()) {
            throw GitException::createFromProcess('Could not execute git command', $process);
        }

        $files = [];
        foreach (\explode(PHP_EOL, $output) as $file) {
            $absolutePath = $git->getRepositoryPath() . '/' . $file;
            if (!$this->config->isPathExcluded($absolutePath)) {
                $files[\trim($absolutePath)] = \trim($absolutePath);
            }
        }

        return $files;
    }

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
     * Retrieve the file path to use in reporting.
     *
     * @return array
     */
    public function getFilePaths()
    {
        $files = [];
        foreach ($this->config->getIncludedPaths() as $path) {
            $files = \array_merge($files, $this->getAllFilesFromGit($this->getGitRepositoryFor($path)));
        }

        return $files;
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

                $process = new Process($arguments, $git->getRepositoryPath());
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

        $process = new Process($arguments, $git->getRepositoryPath());
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
     * Retrieve the data of the current user on the system.
     *
     * @param GitRepository $git The repository to extract all files from.
     *
     * @return string
     *
     * @throws GitException When the git execution failed.
     */
    private function getCurrentUserInfo($git)
    {
        // Sadly no command in our git library for this.
        $arguments = [
            $git->getConfig()->getGitExecutablePath(),
            'config',
            '--get-regexp',
            'user.[name|email]'
        ];

        $process = new Process($arguments, $git->getRepositoryPath());

        $git->getConfig()->getLogger()->debug(
            \sprintf('[git-php] exec [%s] %s', $process->getWorkingDirectory(), $process->getCommandLine())
        );

        $process->run();
        $output = \rtrim($process->getOutput(), "\r\n");

        if (!$process->isSuccessful()) {
            throw GitException::createFromProcess('Could not execute git command', $process);
        }

        $config = [];
        foreach (\explode(PHP_EOL, $output) as $line) {
            list($name, $value)   = \explode(' ', $line, 2);
            $config[\trim($name)] = \trim($value);
        }

        if (isset($config['user.name']) && $config['user.email']) {
            return \sprintf('%s <%s>', $config['user.name'], $config['user.email']);
        }

        return '';
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
