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

use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor;
use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Json\JsonFormatter;

/**
 * Abstract class for author extraction.
 */
abstract class JsonAuthorExtractor extends AbstractPatchingAuthorExtractor
{
    /**
     * Read the .json file and return it as array.
     *
     * @param string $path A path obtained via a prior call to JsonAuthorExtractor::getFilePaths().
     *
     * @return array
     */
    protected function loadFile($path)
    {
        if (!is_file($path)) {
            return null;
        }

        $composerJson = file_get_contents($path);
        return (array) json_decode($composerJson, true);
    }

    /**
     * Encode the json file and return it as string.
     *
     * @param array $json The json data.
     *
     * @return string
     */
    protected function encodeData($json)
    {
        return JsonFormatter::format($json);
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
    abstract protected function setAuthors($json, $authors);

    /**
     * {@inheritDoc}
     */
    public function getBuffer($path, $authors = null)
    {
        $json = $this->loadFile($path);

        if ($authors) {
            $json = $this->setAuthors($json, $this->calculateUpdatedAuthors($path, $authors));
        }

        return $this->encodeData($json);
    }
}
