<?php

/**
 * This file is part of phpcq/author-validation.
 *
 * (c) 2014-2022 Christian Schiffler, Tristan Lins
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
 * @copyright  2014-2022 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

declare(strict_types=1);

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use PhpCodeQuality\AuthorValidation\Json\JsonFormatter;

use function file_get_contents;
use function is_file;
use function json_decode;

/**
 * Trait for author extraction.
 */
trait JsonAuthorExtractorTrait
{
    /**
     * {@inheritDoc}
     */
    public function getBuffer(string $path, ?array $authors = null): ?string
    {
        if (null === $authors) {
            return $this->fileData($path);
        }

        $json = $this->loadFile($path);
        $json = $this->setAuthors($json, $this->calculateUpdatedAuthors($path, $authors));

        return $this->encodeData($json);
    }

    /**
     * Set the author information in the json.
     *
     * @param array $json    The json data.
     * @param array $authors The authors to set in the json.
     *
     * @return array The updated json array.
     */
    abstract protected function setAuthors(array $json, array $authors): array;

    /**
     * Read the .json file and return it as array.
     *
     * @param string $path A path obtained via a prior call to JsonAuthorExtractorTrait::getFilePaths().
     *
     * @return array
     */
    protected function loadFile(string $path): ?array
    {
        $composerJson = $this->fileData($path);

        return (null === $composerJson) ? null : (array) json_decode($composerJson, true);
    }

    /**
     * Encode the json file and return it as string.
     *
     * @param array $json The json data.
     *
     * @return string
     */
    protected function encodeData(array $json): string
    {
        return JsonFormatter::format($json);
    }

    /**
     * Load the file and return its contents.
     *
     * @param string $path Path to the json file.
     *
     * @return string|null
     */
    private function fileData(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        return file_get_contents($path);
    }
}
