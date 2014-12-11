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
     * {@inheritDoc}
     */
    protected function buildFinder()
    {
        return parent::buildFinder()->name('bower.json');
    }

    /**
     * {@inheritDoc}
     */
    protected function doExtract($path)
    {
        $bowerJson = $this->loadFile($path);

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

    /**
     * Set the author information in the json.
     *
     * @param array $json    The json data.
     *
     * @param array $authors The authors to set in the json.
     *
     * @return array The updated json array.
     */
    protected function setAuthors($json, $authors)
    {
        $json['authors'] = array();
        foreach ($authors as $author) {
            $json['authors'][] = $author;
        }

        return $json;
    }
}
