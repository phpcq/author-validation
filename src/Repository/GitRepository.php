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
 * @author     Tristan Lins <tristan@lins.io>
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2022 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

declare(strict_types=1);

namespace PhpCodeQuality\AuthorValidation\Repository;

use Bit3\GitPhp\GitException;
use Bit3\GitPhp\GitRepository as GitPhpRepository;
use PhpCodeQuality\AuthorValidation\Config;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use SebastianBergmann\PHPCPD\Detector\Detector;
use SebastianBergmann\PHPCPD\Detector\Strategy\DefaultStrategy;
use Symfony\Component\Process\Process;

use function array_filter;
use function array_map;
use function array_merge;
use function array_reverse;
use function count;
use function dirname;
use function file_exists;
use function fopen;
use function fwrite;
use function in_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function md5;
use function mkdir;
use function opendir;
use function preg_match_all;
use function readdir;
use function rmdir;
use function rtrim;
use function serialize;
use function sprintf;
use function strlen;
use function strpos;
use function substr;
use function substr_count;
use function sys_get_temp_dir;
use function trim;
use function unlink;

/**
 * The extended git repository.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
final class GitRepository
{
    /**
     * The git repository.
     *
     * @var GitPhpRepository
     */
    private GitPhpRepository $git;

    /**
     * The configuration this extractor shall operate within.
     *
     * @var Config
     */
    private Config $config;

    /**
     * The cache.
     *
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * The commit collection.
     *
     * @var array
     */
    private array $commitCollection = [];

    /**
     * The file path mapping, who maps the file path with the collection.
     *
     * @var array
     */
    private array $filePathMapping = [];

    /**
     * The file path collection, who has all commits for each file.
     *
     * @var array
     */
    private array $filePathCollection = [];

    /**
     * The constructor.
     *
     * @param GitPhpRepository $git    The git repository.
     * @param Config           $config The configuration this extractor shall operate with.
     * @param CacheInterface   $cache  The cache.
     */
    public function __construct(GitPhpRepository $git, Config $config, CacheInterface $cache)
    {
        $this->git    = $git;
        $this->config = $config;
        $this->cache  = $cache;
    }

    /**
     * Analyse the git repository
     *
     * @return void
     */
    public function analyze(): void
    {
        // git show --format="%H" --quiet
        $lastCommitId = $this->git->show()->format('%H')->execute('--quiet');
        $cacheId      = md5(__FUNCTION__ . $lastCommitId);
        if (
            (false !== $this->cache->has($cacheId))
            || (null === $this->cache->get($cacheId))
        ) {
            $this->commitCollection   = [];
            $this->filePathMapping    = [];
            $this->filePathCollection = [];
            $this->buildCollection();

            $this->cache->set(
                $cacheId,
                [
                    'commitCollection'   => $this->commitCollection,
                    'filePathMapping'    => $this->filePathMapping,
                    'filePathCollection' => $this->filePathCollection
                ]
            );
        }
        $cache = $this->cache->get($cacheId);

        $this->commitCollection   = $cache['commitCollection'] ?? [];
        $this->filePathMapping    = $cache['filePathMapping'] ?? [];
        $this->filePathCollection = $cache['filePathCollection'] ?? [];
    }

    /**
     * Check if git repository has uncommitted modifications.
     *
     * @return bool
     */
    public function hasUncommittedChanges(): bool
    {
        $status = $this->git->status()->short()->getIndexStatus();

        if (empty($status)) {
            return false;
        }

        return true;
    }

    /**
     * Build the file history and update the file path collection.
     *
     * @param string $path The file path.
     *
     * @return void
     */
    public function buildFileHistory(string $path): void
    {
        $filePath = $this->getFilePathCollection($path);

        // If the commit history only merges,
        // then use the last merge commit for find the renaming file for follow the history.
        if (count((array) $filePath['commits']) === $this->countMergeCommits($filePath['commits'])) {
            $commit = $filePath['commits'][array_reverse(array_keys($filePath['commits']))[0]];
            if ($this->isMergeCommit($commit)) {
                $parents = explode(' ', $commit['parent']);

                $arguments = [
                    $this->git->getConfig()->getGitExecutablePath(),
                    'diff',
                    $commit['commit'],
                    $parents[1],
                    '--diff-filter=R',
                    '--name-status',
                    '--format=',
                    '-M'
                ];

                // git diff currentCommit rightCommit --diff-filter=R --name-status --format='' -M
                $matches = $this->matchFileInformation($this->runCustomGit($arguments));
                foreach ($matches as $match) {
                    if (!in_array($path, $match, true)) {
                        continue;
                    }

                    $path     = $match['to'];
                    $filePath = $this->getFilePathCollection($path);
                    break;
                }
            }
        }

        $fileHistory = $this->fetchFileHistory($path);
        if (!count($fileHistory)) {
            return;
        }

        foreach ($fileHistory as $pathHistory) {
            $filePath['pathHistory'][md5($pathHistory)] = $this->getFilePathCollection($pathHistory);
        }

        $this->setFilePathCollection($path, $filePath);
    }

    /**
     * Fetch the git log with simplify merges.
     *
     * @return array
     */
    public function fetchAllCommits(): array
    {
        $currentCommit = $this->fetchCurrentCommit();
        $cacheId       = md5(__FUNCTION__ . serialize($currentCommit));
        if (false === $this->cache->has($cacheId)) {
            $logList = json_decode(
                sprintf(
                    '[%s]',
                    trim(
                        // git log --simplify-merges --format=$this->logFormat()
                        $this->git->log()->simplifyMerges()->format($this->logFormat() . ',')->execute(),
                        ','
                    )
                ),
                true
            );

            $this->cache->set($cacheId, $logList);
        }

        return $this->cache->get($cacheId);
    }

    /**
     * Retrieve a list of all files within the git repository.
     *
     * @return string[]
     */
    public function fetchAllFiles(): array
    {
        // Sadly no command in our git library for this.
        $arguments = [
            $this->git->getConfig()->getGitExecutablePath(),
            'ls-tree',
            'HEAD',
            '-r',
            '--full-name',
            '--name-only'
        ];

        $process = new Process($arguments, $this->git->getRepositoryPath());

        $this->git->getConfig()->getLogger()->debug(
            sprintf('[ccabs-repository-git] exec [%s] %s', $process->getWorkingDirectory(), $process->getCommandLine())
        );

        $process->run();
        $output = rtrim($process->getOutput(), "\r\n");

        if (!$process->isSuccessful()) {
            throw GitException::createFromProcess('Could not execute git command', $process);
        }

        $gitDir = $this->git->getRepositoryPath();
        $files  = [];
        foreach (explode(PHP_EOL, $output) as $file) {
            $absolutePath = $gitDir . '/' . $file;
            if (!$this->config->isPathExcluded($absolutePath)) {
                $files[trim($absolutePath)] = trim($absolutePath);
            }
        }

        return $files;
    }

    /**
     * Fetch the current commit.
     *
     * @return array
     */
    public function fetchCurrentCommit(): array
    {
        return json_decode(
            sprintf(
                '[%s]',
                // git show --format=$this->logFormat() --quiet
                $this->git->show()->format($this->logFormat())->execute('--quiet')
            ),
            true
        );
    }

    /**
     * Fetch the file names with status from the commit.
     *
     * @param string $commitId The commit identifier.
     *
     * @return string
     */
    public function fetchNameStatusFromCommit(string $commitId): string
    {
        $arguments = [
            $this->git->getConfig()->getGitExecutablePath(),
            'show',
            $commitId,
            '-M',
            '--name-status',
            '--format='
        ];

        // git show $commitId -M --name-status --format=''
        return $this->runCustomGit($arguments);
    }

    /**
     * Fetch the commit collection by the file path.
     *
     * @param string $path The file path.
     *
     * @return array
     */
    private function fetchCommitCollectionByPath(string $path): array
    {
        // git log --follow --name-status --format='%H' -- $path
        $log =
            $this->git->log()->follow()->revisionRange('--name-status')->revisionRange('--format=%H')->execute($path);

        preg_match_all(
            "/^(?'commit'.*)\n+(?'criteria'[RC])(?'index'[\d]{3})\t(?'from'.+)\t(?'to'.+)\n/m",
            $log,
            $matches,
            PREG_SET_ORDER
        );
        if (!count($matches)) {
            return [];
        }

        $logCollection = [];
        foreach ((array) $matches as $match) {
            $logCollection[] = array_filter($match, '\is_string', ARRAY_FILTER_USE_KEY);
        }

        return $logCollection;
    }

    /**
     * Fetch the history for the file.
     *
     * @param string $path The file path.
     *
     * @return array
     */
    public function fetchFileHistory(string $path): array
    {
        $fileHistory = [];
        foreach ($this->fetchCommitCollectionByPath($path) as $logItem) {
            // If the renaming/copy to not found in the file history, then continue the loop.
            if (count($fileHistory) && !in_array($logItem['to'], $fileHistory, true)) {
                continue;
            }

            // If the file history empty (also by the start for the detection) and the to path not exactly the same
            // with the same path, then continue the loop.
            if (($logItem['to'] !== $path) && !count($fileHistory)) {
                continue;
            }

            $this->executeFollowDetection($logItem, $fileHistory);
        }

        return $fileHistory;
    }

    /**
     * Retrieve the author list from the git repository via calling git.
     *
     * @param string      $type Determine if file should contain authors of the file or the project.
     * @param string|null $path The path.
     *
     * @return array
     */
    public function fetchAuthorListFrom(string $type, string $path = null): array
    {
        if ('project' === $type) {
            return [$this->git->shortlog()->summary()->email()->revisionRange('HEAD')->execute()];
        }

        $filePath = $this->getFilePathCollection($path);

        $authors = [];
        foreach ($filePath['commits'] ?? [] as $commit) {
            if (
                $this->isMergeCommit($commit)
                || isset($authors[md5($commit['name'])])
            ) {
                continue;
            }

            $authors[md5($commit['name'])] = $commit;
        }

        foreach ($filePath['pathHistory'] ?? [] as $pathHistory) {
            foreach ($pathHistory['commits'] ?? [] as $commitHistory) {
                if (
                    $this->isMergeCommit($commitHistory)
                    || isset($authors[md5($commitHistory['name'])])
                ) {
                    continue;
                }

                $authors[md5($commitHistory['name'])] = $commitHistory;
            }
        }

        return $authors;
    }

    /**
     * Get the relative file path.
     *
     * @param string $path The absolute path.
     *
     * @return string
     */
    public function getRelativeFilePath(string $path): string
    {
        if (0 !== strpos($path, $this->git->getRepositoryPath())) {
            return $path;
        }

        return ltrim(substr($path, strlen($this->git->getRepositoryPath())), '/');
    }

    /**
     * Retrieve the data of the current user on the system.
     *
     * @return string
     *
     * @throws GitException When the git execution failed.
     */
    public function getCurrentUserInfo(): string
    {
        // Sadly no command in our git library for this.
        $arguments = [
            $this->git->getConfig()->getGitExecutablePath(),
            'config',
            '--get-regexp',
            'user.[name|email]'
        ];

        $process = new Process($arguments, $this->git->getRepositoryPath());

        $this->git->getConfig()->getLogger()->debug(
            sprintf('[git-php] exec [%s] %s', $process->getWorkingDirectory(), $process->getCommandLine())
        );

        $process->run();
        $output = rtrim($process->getOutput(), "\r\n");

        if (!$process->isSuccessful()) {
            throw GitException::createFromProcess('Could not execute git command', $process);
        }

        $config = [];
        foreach (explode(PHP_EOL, $output) as $line) {
            [$name, $value] = explode(' ', $line, 2);
            $config[trim($name)] = trim($value);
        }

        if (isset($config['user.name']) && $config['user.email']) {
            return sprintf('%s <%s>', $config['user.name'], $config['user.email']);
        }

        return '';
    }

    /**
     * Convert the git binary output to a valid author list.
     *
     * @param string $type    Determine if file should contain authors of the file or the project.
     * @param array  $authors The author list to convert.
     *
     * @return string[]
     */
    public function convertAuthorList(string $type, array $authors): array
    {
        if (empty($authors)) {
            return [];
        }

        if ('project' === $type) {
            return array_map(
                static function ($author) {
                    return preg_replace('~\s*([\d]+)\s+(.*)~', '$2', $author);
                },
                preg_split('~[\r\n]+~', current($authors))
            );
        }

        return array_map(
            static function ($author) {
                return $author['name'] . ' <' . $author['email'] . '>';
            },
            $authors
        );
    }

    /**
     * Use the git repository.
     *
     * @return GitPhpRepository
     */
    public function git(): GitPhpRepository
    {
        return $this->git;
    }

    /**
     * Build the collection.
     *
     * @return void
     */
    private function buildCollection(): void
    {
        $logList = $this->fetchAllCommits();
        foreach ($logList as $log) {
            $currentCacheId = md5(__FUNCTION__ . $log['commit']);
            if ($this->cache->has($currentCacheId)) {
                $fromCurrentCache = $this->cache->get($currentCacheId);

                $this->commitCollection   =
                    array_merge($this->commitCollection, $fromCurrentCache['commitCollection']);
                $this->filePathMapping    =
                    array_merge($this->filePathMapping, $fromCurrentCache['filePathMapping']);
                $this->filePathCollection =
                    array_merge($this->filePathCollection, $fromCurrentCache['filePathCollection']);

                break;
            }

            $matches = $this->matchFileInformation($this->fetchNameStatusFromCommit($log['commit']));
            if (!count($matches)) {
                continue;
            }

            $changeCollection = [];
            foreach ($matches as $match) {
                $changeCollection[] = array_filter(array_filter($match), '\is_string', ARRAY_FILTER_USE_KEY);
            }

            $this->buildChangedCollection($log, $changeCollection);
        }
    }

    /**
     * Build the collection for commit, file path mapping and the file path.
     *
     * @param array $commit           The commit information.
     * @param array $changeCollection The change collection.
     *
     * @return void
     */
    private function buildChangedCollection(array $commit, array $changeCollection): void
    {
        foreach ($changeCollection as $change) {
            if (!isset($commit['containedPath'])) {
                $commit['containedPath'] = [];
            }

            if (!isset($commit['information'])) {
                $commit['information'] = [];
            }

            if (isset($change['criteria'])) {
                $changeToHash   = md5($change['to']);
                $changeFromHash = md5($change['from']);

                $commit['containedPath'][$changeToHash] = $change['to'];
                $commit['information'][$changeToHash]   = $change;

                $this->filePathMapping[$changeToHash]   = $change['to'];
                $this->filePathMapping[$changeFromHash] = $change['from'];

                $this->filePathCollection[$changeToHash]['commits'][$commit['commit']]   = $commit;
                $this->filePathCollection[$changeFromHash]['commits'][$commit['commit']] = $commit;

                $this->commitCollection[$commit['commit']] = $commit;

                continue;
            }

            $fileHash = md5($change['file']);

            $commit['containedPath'][$fileHash] = $change['file'];
            $commit['information'][$fileHash]   = $change;

            $this->filePathMapping[$fileHash] = $change['file'];

            $this->filePathCollection[$fileHash]['commits'][$commit['commit']] = $commit;

            $this->commitCollection[$commit['commit']] = $commit;
        }
    }

    /**
     * Match file information from git repository result.
     *
     * @param string $result The result.
     *
     * @return array
     */
    private function matchFileInformation(string $result): array
    {
        preg_match_all(
            "/^(?(?=[A-Z][\d]{3})(?'criteria'[A-Z])(?'index'[\d]{3})\t(?'from'.+)\t(?'to'.+)$" .
            "|(?'status'[A-Z]{1,2})\t(?'file'.+))$/m",
            $result,
            $matches,
            PREG_SET_ORDER
        );

        return $matches;
    }

    /**
     * Run a custom git process.
     *
     * @param array $arguments A list of git arguments.
     *
     * @return string
     *
     * @throws GitException When the git execution failed.
     */
    private function runCustomGit(array $arguments): string
    {
        $process = new Process($arguments, $this->git->getRepositoryPath());
        $this->git->getConfig()->getLogger()->debug(
            sprintf('[git-php] exec [%s] %s', $process->getWorkingDirectory(), $process->getCommandLine())
        );

        $process->run();
        $result = rtrim($process->getOutput(), "\r\n");

        if (!$process->isSuccessful()) {
            throw GitException::createFromProcess('Could not execute git command', $process);
        }

        return $result;
    }

    /**
     * The log format.
     *
     * @return string
     */
    private function logFormat(): string
    {
        $logFormat = [
            'commit'  => '%H',
            'name'    => '%aN',
            'email'   => '%ae',
            'subject' => '%f',
            'date'    => '%ci',
            'parent'  => '%P'
        ];

        return json_encode($logFormat);
    }

    /**
     * Determine if the commit is a merge commit.
     *
     * @param array $commit The commit information.
     *
     * @return bool
     */
    private function isMergeCommit(array $commit): bool
    {
        return 1 < (substr_count($commit['parent'], ' ') + 1);
    }

    /**
     * Count merge commits from the commit list.
     *
     * @param array $commitList The list of commits.
     *
     * @return int
     */
    private function countMergeCommits(array $commitList): int
    {
        $count = 0;
        foreach ($commitList as $filePathCommit) {
            if (!$this->isMergeCommit($filePathCommit)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Get the data to the file path collection.
     *
     * @param string $path The file path.
     *
     * @return array
     */
    private function getFilePathCollection(string $path): array
    {
        $key = array_flip($this->filePathMapping)[$path];

        return $this->filePathCollection[$key];
    }

    /**
     * Set the data to the file path collection.
     *
     * @param string $path The file path.
     * @param array  $data The file path data.
     *
     * @return void
     */
    private function setFilePathCollection(string $path, array $data): void
    {
        $key = array_flip($this->filePathMapping)[$path];

        $this->filePathCollection[$key] = $data;
    }

    /**
     * Execute the file follow detection.
     *
     * @param array $logItem     The git log item.
     * @param array $fileHistory The file history.
     *
     * @return void
     */
    private function executeFollowDetection(array $logItem, array &$fileHistory): void
    {
        $currentCommit = $this->commitCollection[$logItem['commit']];

        if (
            ($logItem['index'] <= 70)
            && in_array($logItem['criteria'], ['R', 'C'])
            && isset($currentCommit['information'][md5($logItem['to'])])
        ) {
            $pathInformation = $currentCommit['information'][md5($logItem['to'])];

            if (isset($pathInformation['status']) && ('A' === $pathInformation['status'])) {
                return;
            }
        }

        $this->renamingDetection($logItem, $currentCommit, $fileHistory);
        $this->copyDetection($logItem, $fileHistory);
        $this->removeTempDirectory();
    }

    /**
     * Detected file follow by the renaming criteria.
     *
     * @param array            $logItem       The git log item.
     * @param array            $currentCommit The current commit information.
     * @param array            $fileHistory   The file history.
     *
     * @return void
     */
    private function renamingDetection(array $logItem, array $currentCommit, array &$fileHistory): void
    {
        if ('R' !== $logItem['criteria']) {
            return;
        }

        if ((int) $logItem['index'] >= 75) {
            $fileHistory[md5($logItem['from'])] = $logItem['from'];

            return;
        }

        $fromFileContent = $this->getFileContent($currentCommit['parent'] . ':' . $logItem['from']);
        $toFileContent   = $this->getFileContent($logItem['commit'] . ':' . $logItem['to']);
        $tempFrom        = $this->createTempFile($logItem['commit'] . ':' . $logItem['from'], $fromFileContent);
        $tempTo          = $this->createTempFile($logItem['commit'] . ':' . $logItem['to'], $toFileContent);

        $detector = new Detector(new DefaultStrategy());
        $result   = $detector->copyPasteDetection([$tempFrom, $tempTo], 2, 7);

        if (!$result->count()) {
            return;
        }

        $fileHistory[md5($logItem['from'])] = $logItem['from'];
    }

    /**
     * Detected file follow by the copy criteria.
     *
     * @param array            $logItem     The git log item.
     * @param array            $fileHistory The file history.
     *
     * @return void
     */
    private function copyDetection(array $logItem, array &$fileHistory): void
    {
        if ('C' !== $logItem['criteria']) {
            return;
        }

        $fromLastCommit    = $this->commitCollection[$logItem['commit']]['parent'];
        $fromContent       = $this->getFileContent($fromLastCommit . ':' . $logItem['from']);
        $fromContentLength = strlen($fromContent);

        $toContent       = $this->getFileContent($logItem['commit'] . ':' . $logItem['to']);
        $toContentLength = strlen($toContent);

        if ($fromContentLength === $toContentLength) {
            $fileHistory[md5($logItem['from'])] = $logItem['from'];

            return;
        }

        $tempFrom = $this->createTempFile($logItem['commit'] . ':' . $logItem['from'], $fromContent);
        $tempTo   = $this->createTempFile($logItem['commit'] . ':' . $logItem['to'], $toContent);

        $detector = new Detector(new DefaultStrategy());
        $result   = $detector->copyPasteDetection([$tempFrom, $tempTo], 5, 35);

        if (!$result->count()) {
            return;
        }

        $fileHistory[md5($logItem['from'])] = $logItem['from'];
    }

    /**
     * Get the file content.
     *
     * @param string           $search The search key (COMMIT:FILE_PATH).
     *
     * @return string
     */
    private function getFileContent(string $search): string
    {
        $cacheId = md5(__FUNCTION__ . $search);
        if (!$this->cache->has($cacheId)) {
            $fileContent = $this->git->show()->execute($search);

            $this->cache->set($cacheId, $fileContent);

            return $fileContent;
        }

        return $this->cache->get($cacheId);
    }

    /**
     * Create a temporary file.
     *
     * @param string $name    The file name.
     * @param string $content The file content.
     *
     * @return string
     *
     * @throws RuntimeException Throws an exception if the directory not created for the file.
     */
    private function createTempFile(string $name, string $content): string
    {
        $tempDir  = sys_get_temp_dir();
        $fileName = md5($name);
        $filePath = $tempDir . DIRECTORY_SEPARATOR . 'phpcq-author-validation' . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($filePath)) {
            return $filePath;
        }

        if (
            !file_exists(dirname($filePath))
            && !mkdir($concurrentDirectory = dirname($filePath))
            && !is_dir($concurrentDirectory)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $file = fopen($filePath, 'wb');

        fwrite($file, $content);

        return $filePath;
    }

    /**
     * Remove the temporary directory.
     *
     * @return void
     */
    private function removeTempDirectory(): void
    {
        $tempDir       = sys_get_temp_dir();
        $directoryPath = $tempDir . DIRECTORY_SEPARATOR . 'phpcq-author-validation';

        if (!file_exists($directoryPath)) {
            return;
        }

        $directory = opendir($directoryPath);

        while (false !== ($file = readdir($directory))) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            unlink($directoryPath . DIRECTORY_SEPARATOR . $file);
        }

        rmdir($directoryPath);
    }
}
