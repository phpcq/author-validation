<?php

/**
 * This file is part of phpcq/author-validation.
 *
 * (c) 2014-2018 Christian Schiffler, Tristan Lins
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    phpcq/author-validation
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Tristan Lins <tristan@lins.io>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2018 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use PhpCodeQuality\AuthorValidation\Json\JsonFormatter;

/**
 * Trait for author extraction.
 */
trait JsonAuthorExtractorTrait
{
    /**
     * Read the .json file and return it as array.
     *
     * @param string $path A path obtained via a prior call to JsonAuthorExtractorTrait::getFilePaths().
     *
     * @return array
     */
    protected function loadFile($path)
    {
        $composerJson = $this->fileData($path);

        return (null === $composerJson) ? null : (array) \json_decode($composerJson, true);
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
        if ($authors === null) {
            return $this->fileData($path);
        }

        $json = $this->loadFile($path);
        $json = $this->setAuthors($json, $this->calculateUpdatedAuthors($path, $authors));

        return $this->encodeData($json);
    }

    /**
     * Load the file and return its contents.
     *
     * @param string $path Path to the json file.
     *
     * @return string|null
     */
    private function fileData($path)
    {
        if (!\is_file($path)) {
            return null;
        }

        return \file_get_contents($path);
    }
}
