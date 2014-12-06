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

/**
 * Extract the author information from a phpDoc file doc block.
 */
class PhpDocAuthorExtractor extends AbstractAuthorExtractor
{
    /**
     * Retrieve the file path to use in reporting.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->getBaseDir();
    }

    /**
     * Read the composer.json, if it exists and extract the authors.
     *
     * @return string[]|null
     */
    protected function doExtract()
    {
        $pathname = $this->getFilePath();

        if (!is_file($pathname)) {
            return array();
        }

        // 4k ought to be enough of a file header for anyone (I hope).
        $content = file_get_contents($pathname, null, null, null, 4096);
        $closing = strpos($content, '*/');
        if ($closing === false) {
            return array();
        }
        $content = substr($content, 0, ($closing + 2));

        $tokens    = token_get_all($content);
        $docBlocks = array_filter(
            $tokens,
            function ($item) {
                return $item[0] == T_DOC_COMMENT;
            }
        );
        $firstDocBlock = reset($docBlocks);

        if (!preg_match_all('/.*@author\s+(.*)\s*/', $firstDocBlock[1], $matches, PREG_OFFSET_CAPTURE)) {
            return array();
        }

        $mentionedAuthors = array();
        foreach ($matches[1] as $match) {
            $mentionedAuthors[] = $match[0];
        }

        return $mentionedAuthors;
    }
}
