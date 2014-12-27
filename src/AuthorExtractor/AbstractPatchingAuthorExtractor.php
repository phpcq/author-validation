<?php

/**
 * This file is part of phpcq/author-validation.
 *
 * (c) Contao Community Alliance <https://c-c-a.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    phpcq/author-validation
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @copyright  Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan.lins@bit3.de>
 * @link       https://github.com/phpcq/author-validation
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\PatchingExtractor;

/**
 * Abstract class for author extraction.
 */
abstract class AbstractPatchingAuthorExtractor extends AbstractAuthorExtractor implements PatchingExtractor
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
        return array_merge(array_intersect_key($this->extractAuthorsFor($path), $authors), $authors);
    }
}
