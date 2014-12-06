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
 * Extract the author information from a bower.json file.
 */
class BowerAuthorExtractor extends JsonAuthorExtractor
{
    /**
     * Retrieve the file path to use in reporting.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . 'bower.json';
    }

    /**
     * Read the composer.json, if it exists and extract the authors.
     *
     * @return string[]|null
     */
    protected function doExtract()
    {
        $bowerJson = $this->loadFile();

        if ($bowerJson === null) {
            return null;
        }

        if (isset($bowerJson['authors']) && is_array($bowerJson['authors'])) {
            return array();
        }

        $mentionedAuthors = array_map(
            function ($author) {
                if (is_string($author)) {
                    return $author;
                }

                if (isset($author['email'])) {
                    return sprintf(
                        '%s <%s>',
                        $author['name'],
                        $author['email']
                    );
                }

                return $author['name'];
            },
            $bowerJson['authors']
        );

        return $mentionedAuthors;
    }
}
