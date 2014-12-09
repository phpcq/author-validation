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

namespace ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation;

/**
 * Interface for an author information extractor.
 */
interface AuthorExtractor
{
    /**
     * Retrieve the file path to use in reporting.
     *
     * @return string
     */
    public function getFilePath();

    /**
     * Retrieve the base dir of this extractor.
     *
     * @return string
     */
    public function getBaseDir();

    /**
     * Set the authors to be ignored.
     *
     * @param array $ignoredAuthors The authors to be ignored.
     *
     * @return string[]
     */
    public function setIgnoredAuthors(array $ignoredAuthors);

    /**
     * Get the authors to be ignored.
     *
     * @return string[]
     */
    public function getIgnoredAuthors();

    /**
     * Retrieve the contained authors.
     *
     * @return string
     */
    public function extractAuthors();
}
