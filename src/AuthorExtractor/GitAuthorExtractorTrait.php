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
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     Tristan Lins <tristan@lins.io>
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2014-2022 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

declare(strict_types=1);

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use PhpCodeQuality\AuthorValidation\Repository\GitRepository;
use RuntimeException;

/**
 * Base trait for author extraction from a git repository.
 */
trait GitAuthorExtractorTrait
{
    /**
     * The extended git repository.
     *
     * @var GitRepository
     */
    protected GitRepository $repository;

    /**
     * Call the internal git repository.
     *
     * @return GitRepository
     */
    public function repository(): GitRepository
    {
        return $this->repository;
    }

    /**
     * Set the extended git repository.
     *
     * @param GitRepository $repository
     *
     * @throw RuntimeException Throws if the repository been replaced.
     */
    public function setRepository(GitRepository $repository): void
    {
        if (isset($this->repository)) {
            throw new RuntimeException('The repository can not replaced.');
        }

        $this->repository = $repository;
    }

    /**
     * Retrieve the file path to use in reporting.
     *
     * @return array
     */
    public function getFilePaths(): array
    {
        return $this->repository->fetchAllFiles();
    }
}
