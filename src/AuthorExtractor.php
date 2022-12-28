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
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2022 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation;

/**
 * Interface for an author information extractor.
 */
interface AuthorExtractor
{
    /**
     * Retrieve the file paths this extractor knows about.
     *
     * @return array
     */
    public function getFilePaths(): array;

    /**
     * Retrieve the contained authors for a path.
     *
     * @param string $path A path obtained via a prior call to AuthorExtractor::getFilePaths().
     *
     * @return string[]|null
     *
     * @see AuthorExtractor::getFilePaths()
     */
    public function extractAuthorsFor(string $path): ?array;

    /**
     * Retrieve the multiple contained authors for a path.
     *
     * @param string $path A path obtained via a prior call to AuthorExtractor::getFilePaths().
     *
     * @return string[]
     *
     * @see AuthorExtractor::getFilePaths()
     */
    public function extractMultipleAuthorsFor(string $path): array;
}
