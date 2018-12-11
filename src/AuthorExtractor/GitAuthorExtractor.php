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
use SebastianBergmann\PHPCPD\Detector\Detector;
use SebastianBergmann\PHPCPD\Detector\Strategy\DefaultStrategy;

/**
 * Extract the author information from a git repository.
 */
class GitAuthorExtractor implements AuthorExtractor
{
    use AuthorExtractorTrait;
    use GitAuthorExtractorTrait;

    /**
     * File mapping list.
     *
     * [md5 hash => file path].
     *
     * @var array
     */
    private $filePathMapping = [];

    /**
     * The file path collection with commits and path history.
     *
     * [md5 file path hash => [
     *      'commits'     => [commit1, commit2],
     *      'pathHistory' => [path1, path2]
     * ]
     *
     * @var array
     */
    private $filePathCollection = [];

    /**
     * The collection of commits.
     *
     * @var array
     */
    private $commitCollection = [];

    /**
     * The current path.
     *
     * @var string
     */
    private $currentPath;

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

        $status  = (array) $git->status()->short()->getIndexStatus();
        $relPath = (string) \substr($path, (\strlen($git->getRepositoryPath()) + 1));

        if (isset($status[$relPath]) && $status[$relPath]) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve the author list from the given path via calling git.
     *
     * @return array
     */
    private function getAuthorListFrom()
    {
        $filePath = $this->getFilePathCollection($this->currentPath);

        $authors = [];
        foreach ((array) $filePath['commits'] as $commit) {
            if ($this->isMergeCommit($commit)
                || isset($authors[\md5($commit['name'])])
            ) {
                continue;
            }

            $authors[\md5($commit['name'])] = $commit;
        }

        if (isset($filePath['pathHistory'])) {
            foreach ((array) $filePath['pathHistory'] as $pathHistory) {
                foreach ((array) $pathHistory['commits'] as $commitHistory) {
                    if ($this->isMergeCommit($commitHistory)
                        || isset($authors[\md5($commitHistory['name'])])
                    ) {
                        continue;
                    }

                    $authors[\md5($commitHistory['name'])] = $commitHistory;
                }
            }
        }

        return $authors;
    }

    /**
     * Determine if the commit is a merge commit.
     *
     * @param array $commit The commit information.
     *
     * @return bool
     */
    private function isMergeCommit(array $commit)
    {
        return 1 < (substr_count($commit['parent'], ' ') + 1);
    }

    /**
     * The log format.
     *
     * @return string
     */
    private function logFormat()
    {
        $logFormat = [
            'commit'  => '%H',
            'name'    => '%aN',
            'email'   => '%ae',
            'subject' => '%f',
            'date'    => '%ci',
            'parent'  => '%P'
        ];

        return \json_encode($logFormat);
    }

    /**
     * Collect all files with their commits.
     *
     * @param GitRepository $git The git repository.
     *
     * @return void
     */
    public function collectFilesWithCommits(GitRepository $git)
    {
        // git show --format="%H" --quiet
        $lastCommitId = $git->show()->format('%H')->execute('--quiet');
        $cacheId      = \md5(__FUNCTION__ . $lastCommitId);

        if ($this->cachePool->has($cacheId)) {
            $fromCache = $this->cachePool->get($cacheId);

            $this->commitCollection   = $fromCache['commitCollection'];
            $this->filePathMapping    = $fromCache['filePathMapping'];
            $this->filePathCollection = $fromCache['filePathCollection'];

            return;
        }

        $logList = $this->fetchAllCommits($git);

        $commitCollection   = [];
        $filePathMapping    = [];
        $filePathCollection = [];
        foreach ($logList as $log) {
            $currentCacheId = \md5(__FUNCTION__ . $log['commit']);
            if ($this->cachePool->has($currentCacheId)) {
                $fromCurrentCache = $this->cachePool->get($currentCacheId);

                $commitCollection   = \array_merge($filePathMapping, $fromCurrentCache['commitCollection']);
                $filePathMapping    = \array_merge($filePathMapping, $fromCurrentCache['filePathMapping']);
                $filePathCollection = \array_merge($filePathCollection, $fromCurrentCache['filePathCollection']);

                break;
            }

            $result = $this->fetchNameStatusFromCommit($log['commit'], $git);
            \preg_match_all(
                // @codingStandardsIgnoreStart
                "/^(?(?=[A-Z][\d]{3})(?'criteria'[A-Z])(?'index'[\d]{3}|)\s+(?'from'\S*)\s+(?'to'\S*)$|(?'status'[A-Z]{1,2})\s+(?'file'\S*))$/m",
                // @codingStandardsIgnoreEnd
                $result,
                $matches,
                PREG_SET_ORDER
            );
            if (!\count($matches)) {
                continue;
            }

            $changeCollection = [];
            foreach ((array) $matches as $match) {
                $changeCollection[] = \array_filter(\array_filter($match), 'is_string', ARRAY_FILTER_USE_KEY);
            }

            $this->prepareChangeCollection(
                $log,
                $changeCollection,
                $commitCollection,
                $filePathMapping,
                $filePathCollection
            );
        }

        $this->cachePool->set(
            $cacheId,
            [
                'commitCollection'   => $commitCollection,
                'filePathMapping'    => $filePathMapping,
                'filePathCollection' => $filePathCollection
            ]
        );

        $this->commitCollection   = $commitCollection;
        $this->filePathMapping    = $filePathMapping;
        $this->filePathCollection = $filePathCollection;
    }

    /**
     * Prepare the collection for commit, file path mapping and the file path.
     *
     * @param array $commit             The commit information.
     * @param array $changeCollection   The change collection.
     * @param array $commitCollection   The commit collection.
     * @param array $filePathMapping    The file path mapping.
     * @param array $filePathCollection The file path collection.
     *
     * @return void
     */
    private function prepareChangeCollection(
        array $commit,
        array $changeCollection,
        array &$commitCollection,
        array &$filePathMapping,
        array &$filePathCollection
    ) {
        foreach ($changeCollection as $change) {
            if (!isset($commit['containedPath'])) {
                $commit['containedPath'] = [];
            }

            if (!isset($commit['information'])) {
                $commit['information'] = [];
            }

            if (isset($change['criteria'])) {
                $changeToHash   = \md5($change['to']);
                $changeFromHash = \md5($change['from']);

                $commit['containedPath'][$changeToHash] = $change['to'];
                $commit['information'][$changeToHash]   = $change;

                $filePathMapping[$changeToHash]   = $change['to'];
                $filePathMapping[$changeFromHash] = $change['from'];

                $filePathCollection[$changeToHash]['commits'][$commit['commit']]   = $commit;
                $filePathCollection[$changeFromHash]['commits'][$commit['commit']] = $commit;

                $commitCollection[$commit['commit']] = $commit;

                continue;
            }

            $fileHash = \md5($change['file']);

            $commit['containedPath'][$fileHash] = $change['file'];
            $commit['information'][$fileHash]   = $change;

            $filePathMapping[$fileHash] = $change['file'];

            $filePathCollection[$fileHash]['commits'][$commit['commit']] = $commit;

            $commitCollection[$commit['commit']] = $commit;
        }
    }

    /**
     * Get the data to the file path collection.
     *
     * @param string $path The file path.
     *
     * @return array
     */
    private function getFilePathCollection($path)
    {
        $key = \array_flip($this->filePathMapping)[$path];

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
    private function setFilePathCollection($path, array $data)
    {
        $key = \array_flip($this->filePathMapping)[$path];

        $this->filePathCollection[$key] = $data;
    }

    /**
     * Build the file history.
     *
     * @param GitRepository $git The git repository.
     *
     * @return void
     */
    private function buildFileHistory(GitRepository $git)
    {
        $filePath    = $this->getFilePathCollection($this->currentPath);
        $fileHistory = $this->fetchFileHistory($this->currentPath, $git);
        if (!\count($fileHistory)) {
            return;
        }

        foreach ($fileHistory as $pathHistory) {
            $filePath['pathHistory'][\md5($pathHistory)] = $this->getFilePathCollection($pathHistory);
        }

        $this->setFilePathCollection($this->currentPath, $filePath);
    }

    /**
     * Get the file content.
     *
     * @param string        $search The search key (COMMIT:FILE_PATH).
     * @param GitRepository $git    The git repository.
     *
     * @return string
     */
    private function getFileContent($search, GitRepository $git)
    {
        $cacheId = \md5(__FUNCTION__ . $search);
        if (!$this->cachePool->has($cacheId)) {
            $fileContent = $git->show()->execute($search);

            $this->cachePool->set($cacheId, $fileContent);

            return $fileContent;
        }

        return $this->cachePool->get($cacheId);
    }

    /**
     * Fetch the file names with status from the commit.
     *
     * @param string        $commitId The commit identifier.
     * @param GitRepository $git      The git repository.
     *
     * @return string
     */
    private function fetchNameStatusFromCommit($commitId, GitRepository $git)
    {
        $arguments = [
            $git->getConfig()->getGitExecutablePath(),
            'show',
            $commitId,
            '-M',
            '--name-status',
            '--format='
        ];

        // git show $commitId --name-status --format=''
        return $this->runCustomGit($arguments, $git);
    }

    /**
     * Fetch the current commit.
     *
     * @param GitRepository $git The git repository.
     *
     * @return array
     */
    private function fetchCurrentCommit(GitRepository $git)
    {
        return \json_decode(
            \sprintf(
                '[%s]',
                // git show --format=$this->logFormat() --quiet
                $git->show()->format($this->logFormat())->execute('--quiet')
            ),
            true
        );
    }

    /**
     * Fetch the git log with simplify merges.
     *
     * @param GitRepository $git The git repository.
     *
     * @return array
     */
    private function fetchAllCommits(GitRepository $git)
    {
        $currentCommit = $this->fetchCurrentCommit($git);

        $cacheId = \md5(__FUNCTION__ . \serialize($currentCommit));

        if (!$this->cachePool->has($cacheId)) {
            $logList = \json_decode(
                \sprintf(
                    '[%s]',
                    \trim(
                        // git log --simplify-merges --format=$this->logFormat()
                        $git->log()->simplifyMerges()->format($this->logFormat() . ',')->execute(),
                        ','
                    )
                ),
                true
            );

            $this->cachePool->set($cacheId, $logList);
        }

        return $this->cachePool->get($cacheId);
    }

    /**
     * Fetch the history for the file.
     *
     * @param string        $path The file path.
     * @param GitRepository $git  The git repository.
     *
     * @return array
     */
    private function fetchFileHistory($path, GitRepository $git)
    {
        $fileHistory = [];
        foreach ($this->fetchCommitCollectionByPath($path, $git) as $logItem) {
            // If the renaming/copy to not found in the file history, then continue the loop.
            if (\count($fileHistory) && !\in_array($logItem['to'], $fileHistory)) {
                continue;
            }

            // If the file history empty (also by the start for the detection) and the to path not exactly the same
            // with the same path, then continue the loop.
            if (($logItem['to'] !== $path) && !\count($fileHistory)) {
                continue;
            }

            $this->executeFollowDetection($logItem, $fileHistory, $git);
        }

        return $fileHistory;
    }

    /**
     * Fetch the commit collection by the file path.
     *
     * @param string        $path The file path.
     * @param GitRepository $git  The git repository.
     *
     * @return array
     */
    private function fetchCommitCollectionByPath($path, GitRepository $git)
    {
        // git log --follow --name-status --format=%H' -- $path
        $log = $git->log()->follow()->revisionRange('--name-status')->revisionRange('--format=%H')->execute($path);

        \preg_match_all(
            "/^(?'commit'.*)\n+(?'criteria'[RC])(?'index'[\d]{3})\s+(?'from'\S*)\s+(?'to'\S*)\n/m",
            $log,
            $matches,
            PREG_SET_ORDER
        );
        if (!\count($matches)) {
            return [];
        }

        $logCollection = [];
        foreach ((array) $matches as $match) {
            $logCollection[] = \array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return $logCollection;
    }

    /**
     * Execute the file follow detection.
     *
     * @param array         $logItem     The git log item.
     * @param array         $fileHistory The file history.
     * @param GitRepository $git         The git repository.
     *
     * @return void
     */
    private function executeFollowDetection(array $logItem, array &$fileHistory, GitRepository $git)
    {
        $currentCommit = $this->commitCollection[$logItem['commit']];

        if (($logItem['index'] <= 70) && \in_array($logItem['criteria'], ['R', 'C'])) {
            if (isset($currentCommit['information'][\md5($logItem['to'])])) {
                $pathInformation = $currentCommit['information'][\md5($logItem['to'])];

                if (isset($pathInformation['status']) && ($pathInformation['status'] === 'A')) {
                    return;
                }
            }
        }

        $this->renamingDetection($logItem, $currentCommit, $fileHistory, $git);
        $this->copyDetection($logItem, $fileHistory, $git);
    }

    /**
     * Detected file follow by the renaming criteria.
     *
     * @param array         $logItem       The git log item.
     * @param array         $currentCommit The current commit information.
     * @param array         $fileHistory   The file history.
     * @param GitRepository $git           The git repository.
     *
     * @return void
     */
    private function renamingDetection(array $logItem, array $currentCommit, array &$fileHistory, GitRepository $git)
    {
        if ($logItem['criteria'] !== 'R') {
            return;
        }

        if ((int) $logItem['index'] >= 75) {
            $fileHistory[\md5($logItem['from'])] = $logItem['from'];

            return;
        }

        $fromFileContent = $this->getFileContent($currentCommit['parent'] . ':' . $logItem['from'], $git);
        $toFileContent   = $this->getFileContent($logItem['commit'] . ':' . $logItem['to'], $git);
        $tempFrom        =
            $this->createTempFile($logItem['commit'] . ':' . $logItem['from'], $fromFileContent);
        $tempTo          = $this->createTempFile($logItem['commit'] . ':' . $logItem['to'], $toFileContent);

        $detector = new Detector(new DefaultStrategy());
        $result   = $detector->copyPasteDetection([$tempFrom, $tempTo], 2, 7);

        if (!$result->count()) {
            return;
        }

        $fileHistory[\md5($logItem['from'])] = $logItem['from'];
    }

    /**
     * Detected file follow by the copy criteria.
     *
     * @param array         $logItem     The git log item.
     * @param array         $fileHistory The file history.
     * @param GitRepository $git         The git repository.
     *
     * @return void
     */
    private function copyDetection(array $logItem, array &$fileHistory, GitRepository $git)
    {
        if ($logItem['criteria'] !== 'C') {
            return;
        }

        $fromLastCommit        = $this->commitCollection[$logItem['commit']]['parent'];
        $fromFileContent       = $this->getFileContent($fromLastCommit . ':' . $logItem['from'], $git);
        $fromFileContentLength = \strlen($fromFileContent);

        $toFileContent       = $this->getFileContent($logItem['commit'] . ':' . $logItem['to'], $git);
        $toFileContentLength = \strlen($toFileContent);

        if ($fromFileContentLength === $toFileContentLength) {
            $fileHistory[\md5($logItem['from'])] = $logItem['from'];

            return;
        }

        $tempFrom =
            $this->createTempFile($logItem['commit'] . ':' . $logItem['from'], $fromFileContent);
        $tempTo   = $this->createTempFile($logItem['commit'] . ':' . $logItem['to'], $toFileContent);

        $detector = new Detector(new DefaultStrategy());
        $result   = $detector->copyPasteDetection([$tempFrom, $tempTo], 5, 35);

        if (!$result->count()) {
            return;
        }

        $fileHistory[\md5($logItem['from'])] = $logItem['from'];
    }

    /**
     * Create a temporary file.
     *
     * @param string $name    The file name.
     * @param string $content The file content.
     *
     * @return string
     *
     * @throws \RuntimeException Throws an exception if the directory not created for the file.
     */
    private function createTempFile($name, $content)
    {
        $cacheDir = $this->cachePool->get('cacheDir');
        $fileName = \md5($name);
        $filePath = $cacheDir . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . $fileName;

        if (\file_exists($filePath)) {
            return $filePath;
        }

        if (!\file_exists(\dirname($filePath)) && !mkdir($concurrentDirectory = \dirname($filePath))
            && !is_dir(
                $concurrentDirectory
            )) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $file = fopen($filePath, 'wb');

        \fwrite($file, $content);

        return $filePath;
    }

    /**
     * Remove the temporary directory.
     *
     * @return void
     */
    private function removeTempDirectory()
    {
        $cacheDir      = $this->cachePool->get('cacheDir');
        $directoryPath = $cacheDir . DIRECTORY_SEPARATOR . 'temp';

        if (!\file_exists($directoryPath)) {
            return;
        }

        $directory = \opendir($directoryPath);

        while (($file = readdir($directory)) !== false) {
            if (\in_array($file, ['.', '..'])) {
                continue;
            }

            unlink($directoryPath . DIRECTORY_SEPARATOR . $file);
        }

        \rmdir($directoryPath);
    }

    /**
     * Perform the extraction of authors.
     *
     * @param string $path A path obtained via a prior call to AuthorExtractor::getFilePaths().
     *
     * @return string[]|null
     *
     * @throws \ReflectionException Which is not available on your PHP installation.
     */
    protected function doExtract($path)
    {
        $git               = $this->getGitRepositoryFor($path);
        $this->currentPath = \substr($path, (\strlen($git->getRepositoryPath()) + 1));

        $this->buildFileHistory($git);
        $this->removeTempDirectory();

        $authors = $this->convertAuthorList($this->getAuthorListFrom());

        // Check if the file path is a file, if so, we need to check if it is "dirty" and someone is currently working
        // on it.
        if ($this->isDirtyFile($path, $git)) {
            $authors[] = $this->getCurrentUserInfo($git);
        }

        return $authors;
    }
}
