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

/**
 * Trait for author extraction.
 */
trait PatchingAuthorExtractorTrait
{
    /**
     * Calculate the updated author map.
     *
     * The passed authors will be used as new reference, all existing not mentioned anymore will not be contained in
     * the result.
     *
     * @param string $path    A path obtained via a prior call to AuthorExtractor::getFilePaths().
     *
     * @param string $authors The new author list.
     *
     * @return \string[]
     */
    protected function calculateUpdatedAuthors($path, $authors)
    {
        return \array_merge(\array_intersect_key($this->extractAuthorsFor($path), $authors), $authors);
    }
}
