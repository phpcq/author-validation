<?php

/**
 * This file is part of contao-community-alliance/build-system-tool-author-validation.
 *
 * (c) Contao Community Alliance <https://c-c-a.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/build-system-tool-author-validation
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  Contao Community Alliance <https://c-c-a.org>
 * @link       https://github.com/contao-community-alliance/build-system-tool-author-validation
 * @license    https://github.com/contao-community-alliance/build-system-tool-author-validation/blob/master/LICENSE MIT
 * @filesource
 */

namespace ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor;

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
     * @param string $authors The new author list.
     *
     * @return \string[]
     */
    public function calculateUpdatedAuthors($authors)
    {
        return array_merge(array_intersect_key($this->extractAuthors(), $authors), $authors);
    }
}
