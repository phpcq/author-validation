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

/**
 * Extract the author information from a composer.json file.
 */
class ComposerAuthorExtractor extends JsonAuthorExtractor
{
    /**
     * {@inheritDoc}
     */
    protected function buildFinder()
    {
        return parent::buildFinder()->name('composer.json');
    }

    /**
     * {@inheritDoc}
     */
    protected function doExtract($path)
    {
        $composerJson = $this->loadFile($path);

        if ($composerJson === null) {
            return null;
        }

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
            list($name, $email) = explode(' <', $author);

            $json['authors'][] = array(
                'name'     => trim($name),
                'email'    => trim(substr($email, 0, -1)),
                'role'     => 'Developer'
            );
        }

        return $json;
    }
}
