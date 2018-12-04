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
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     Tristan Lins <tristan@lins.io>
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2014-2018 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use Bit3\GitPhp\GitException;
use Bit3\GitPhp\GitRepository;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

/**
 * Base trait for author extraction from a git repository.
 */
trait GitAuthorExtractorTrait
{
    /**
     * Create a git repository instance.
     *
     * @param string $path A path within a git repository.
     *
     * @return GitRepository.
     */
    protected function getGitRepositoryFor($path)
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
     * Retrieve a list of all files within a git repository.
     *
     * @param GitRepository $git The repository to extract all files from.
     *
     * @return string[]
     *
     * @throws GitException When the git execution failed.
     * @throws \ReflectionException Thrown if the class does not exist.
     */
    private function getAllFilesFromGit($git)
    {
        $gitDir = $git->getRepositoryPath();
        // Sadly no command in our git library for this.
        $arguments = [
            $git->getConfig()->getGitExecutablePath(),
            'ls-tree',
            'HEAD',
            '-r',
            '--full-name',
            '--name-only'
        ];

        $process = new Process($this->prepareProcessArguments($arguments), $git->getRepositoryPath());

        $git->getConfig()->getLogger()->debug(
            \sprintf('[ccabs-repository-git] exec [%s] %s', $process->getWorkingDirectory(), $process->getCommandLine())
        );

        $process->run();
        $output = \rtrim($process->getOutput(), "\r\n");

        if (!$process->isSuccessful()) {
            throw GitException::createFromProcess('Could not execute git command', $process);
        }

        $files = [];
        foreach (\explode(PHP_EOL, $output) as $file) {
            $absolutePath = $gitDir . '/' . $file;
            if (!$this->config->isPathExcluded($absolutePath)) {
                $files[\trim($absolutePath)] = \trim($absolutePath);
            }
        }

        return $files;
    }

    /**
     * Retrieve the file path to use in reporting.
     *
     * @return array
     *
     * @throws \ReflectionException Thrown if the class does not exist.
     */
    public function getFilePaths()
    {
        $files = [];
        foreach ($this->config->getIncludedPaths() as $path) {
            $files[] = $this->getAllFilesFromGit($this->getGitRepositoryFor($path));
        }

        return \call_user_func_array('array_merge', $files);
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
     * Retrieve the data of the current user on the system.
     *
     * @param GitRepository $git The repository to extract all files from.
     *
     * @return string
     *
     * @throws GitException When the git execution failed.
     * @throws \ReflectionException Thrown if the class does not exist.
     */
    protected function getCurrentUserInfo($git)
    {
        // Sadly no command in our git library for this.
        $arguments = [
            $git->getConfig()->getGitExecutablePath(),
            'config',
            '--get-regexp',
            'user.[name|email]'
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

        $config = array();
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
     * Run a custom git process.
     *
     * @param array         $arguments A list of git arguments.
     * @param GitRepository $git       The git repository.
     *
     * @return string
     *
     * @throws GitException When the git execution failed.
     * @throws \ReflectionException Thrown if the class does not exist.
     */
    private function runCustomGit(array $arguments, GitRepository $git)
    {
        $process = new Process($this->prepareProcessArguments($arguments), $git->getRepositoryPath());
        $git->getConfig()->getLogger()->debug(
            \sprintf('[git-php] exec [%s] %s', $process->getWorkingDirectory(), $process->getCommandLine())
        );

        $process->run();
        $result = rtrim($process->getOutput(), "\r\n");

        if (!$process->isSuccessful()) {
            throw GitException::createFromProcess('Could not execute git command', $process);
        }

        return $result;
    }

    /**
     * Prepare the command line arguments for the symfony process.
     *
     * @param array $arguments The command line arguments for the symfony process.
     *
     * @return array|string
     *
     * @throws \ReflectionException Thrown if the class does not exist.
     */
    protected function prepareProcessArguments(array $arguments)
    {
        $reflection = new \ReflectionClass(ProcessUtils::class);

        if (!$reflection->hasMethod('escapeArgument')) {
            return $arguments;
        }

        return \implode(' ', \array_map([ProcessUtils::class, 'escapeArgument'], $arguments));
    }
}
