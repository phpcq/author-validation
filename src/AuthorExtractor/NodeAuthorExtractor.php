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

use PhpCodeQuality\AuthorValidation\AuthorExtractor;
use PhpCodeQuality\AuthorValidation\PatchingExtractor;

use function array_shift;
use function explode;
use function sprintf;
use function trim;

/**
 * Extract the author information from a nodeJs packages.json file.
 */
class NodeAuthorExtractor implements AuthorExtractor, PatchingExtractor
{
    use AuthorExtractorTrait;
    use PatchingAuthorExtractorTrait;
    use JsonAuthorExtractorTrait;

    /**
     * {@inheritDoc}
     */
    protected function buildFinder()
    {
        return $this->setupFinder()->name('packages.json');
    }

    /**
     * {@inheritDoc}
     */
    protected function doExtract(string $path): ?array
    {
        $packagesJson = $this->loadFile($path);

        if ($packagesJson === null) {
            return null;
        }

        $mentionedAuthors = [];

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
            foreach ((array) $packagesJson['contributors'] as $contributor) {
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
     * @param array $authors The authors to set in the json.
     *
     * @return array The updated json array.
     */
    protected function setAuthors(array $json, array $authors): array
    {
        // If no author set yet, use the first one as author.
        if (!isset($json['author'])) {
            $json['author'] = array_shift($authors);
        }
        $maintainer = $this->convertAuthor($json['author']);

        foreach ($authors as $author) {
            $converted = $this->convertAuthor($author);

            // Ignore the maintainer here.
            if ($converted['email'] === $maintainer['email']) {
                continue;
            }

            $json['contributors'][] = $converted;
        }

        return $json;
    }

    /**
     * Convert an author in the form of "Author Name <mail@example.org>" to author array.
     *
     * @param string $author The author.
     *
     * @return array
     */
    private function convertAuthor(string $author): array
    {
        [$name, $email] = explode(' <', $author);
        return [
            'name'     => trim($name),
            'email'    => trim(\substr($email, 0, -1)),
        ];
    }
}
