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
 * Extract the author information from a nodeJs packages.json file.
 */
class NodeAuthorExtractor extends JsonAuthorExtractor
{
    /**
     * Retrieve the file path to use in reporting.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->getBaseDir() . DIRECTORY_SEPARATOR . 'packages.json';
    }

    /**
     * Read the composer.json, if it exists and extract the authors.
     *
     * @return string[]|null
     */
    protected function doExtract()
    {
        $packagesJson = $this->loadFile();

        if ($packagesJson === null) {
            return null;
        }

        $mentionedAuthors = array();

        if (isset($packagesJson['author'])) {
            if (isset($packagesJson['author']['email'])) {
                $mentionedAuthors[] = sprintf(
                    '%s <%s>',
                    $packagesJson['author']['name'],
                    $packagesJson['author']['email']
                );
            } else {
                $mentionedAuthors[] = $packagesJson['author']['name'];
            }
        }

        if (isset($packagesJson['contributors'])) {
            foreach ($packagesJson['contributors'] as $contributor) {
                if (isset($contributor['email'])) {
                    $mentionedAuthors[] = sprintf(
                        '%s <%s>',
                        $contributor['name'],
                        $contributor['email']
                    );
                } else {
                    $mentionedAuthors[] = $contributor['name'];
                }
            }
        }

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
        $key = (!isset($json['authors']['contributors'])) ? 'author' : 'contributors';

        // FIXME: this does not correctly update the data as we have two arrays to search.

        foreach ($this->calculateUpdatedAuthors($authors) as $author) {
            list($name, $email) = explode(' <', $author);

            $json[$key][] = array(
                'name'     => trim($name),
                'email'    => trim(substr($email, 0, -1)),
            );
        }

        return $json;
    }
}
