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

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use function is_file;

/**
 * Extract the author information from a git repository.
 */
class GitAuthorExtractor implements GitTypeAuthorExtractor
{
    use AuthorExtractorTrait;
    use GitAuthorExtractorTrait;

    public const TYPE = 'file';

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * Check if the current file path is a file and if so, if it has staged modifications.
     *
     * @param string $path A path obtained via a prior call to AuthorExtractor::getFilePaths().
     *
     * @return bool
     */
    private function isDirtyFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $status  = $this->repository->git()->status()->short()->getIndexStatus();
        $relPath = $this->repository->getRelativeFilePath($path);

        return isset($status[$relPath]) && $status[$relPath];
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
        $currentPath = $this->repository->getRelativeFilePath($path);

        $this->repository->buildFileHistory($currentPath);

        $authors = $this->repository->convertAuthorList(
            $this->getType(),
            $this->repository->fetchAuthorListFrom($this->getType(), $currentPath)
        );

        // Check if the file path is a file, if so, we need to check if it is "dirty" and someone is currently working
        // on it.
        if ($this->isDirtyFile($path)) {
            $authors[] = $this->repository->getCurrentUserInfo();
        }

        return $authors;
    }
}
