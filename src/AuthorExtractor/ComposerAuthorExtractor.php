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

        $config           = $this->config;
        $mentionedAuthors = array_map(
            function ($author) use ($config) {
                if (isset($author['email'])) {
                    $author['name'] = sprintf(
                        '%s <%s>',
                        $author['name'],
                        $author['email']
                    );
                }

                // set role metadata if not already set.
                if (isset($author['role']) && !$config->hasMetadata($author['name'], 'role')) {
                    $config->setMetadata($author['name'], 'role', $author['role']);
                }

                // set homepage metadata if not already set.
                if (isset($author['homepage']) && !$config->hasMetadata($author['name'], 'homepage')) {
                    $config->setMetadata($author['name'], 'homepage', $author['homepage']);
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

            $config = array(
                'name'     => trim($name),
                'email'    => trim(substr($email, 0, -1))
            );

            if ($this->config->hasMetadata($author, 'homepage')) {
                $config['homepage'] = $this->config->getMetadata($author, 'homepage');
            }

            $config['role'] = $this->config->getMetadata($author, 'role') ?: 'Developer';

            $json['authors'][] = $config;
        }

        return $json;
    }
}
