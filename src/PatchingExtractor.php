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
 * Interface for an author information extractor that can patch it's input.
 */
interface PatchingExtractor extends AuthorExtractor
{
    /**
     * Update author list in the storage with the given authors.
     *
     * @param string     $path     A path obtained via a prior call to AuthorExtractor::getFilePaths().
     * @param array|null $authors The author list that shall be used in the resulting buffer
     *                            (optional, if empty the buffer is unchanged).
     *
     * @return string|null The new storage content with the updated author list.
     */
    public function getBuffer(string $path, ?array $authors = null): ?string;
}
