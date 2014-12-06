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
 * Extract the author information from a composer.json file.
 */
class ComposerAuthorExtractor extends AbstractAuthorExtractor
{
    /**
     * Retrieve the file path to use in reporting.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . 'composer.json';
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
            return null;
        }

        $composerJson = file_get_contents($pathname);
        $composerJson = (array) json_decode($composerJson, true);

        if (!(isset($composerJson['authors']) && is_array($composerJson['authors']))) {
            return array();
        }

        $mentionedAuthors = array_map(
            function ($author) {
                if (isset($author['email'])) {
                    return sprintf(
                        '%s <%s>',
                        $author['name'],
                        $author['email']
                    );
                }

                return $author['name'];
            },
            $composerJson['authors']
        );

        return $mentionedAuthors;
    }
}
