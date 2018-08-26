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

use PhpCodeQuality\AuthorValidation\AuthorExtractor;
use PhpCodeQuality\AuthorValidation\PatchingExtractor;

/**
 * Extract the author information from a bower.json file.
 */
class BowerAuthorExtractor implements AuthorExtractor, PatchingExtractor
{
    use AuthorExtractorTrait;
    use PatchingAuthorExtractorTrait;
    use JsonAuthorExtractorTrait;

    /**
     * {@inheritDoc}
     */
    protected function buildFinder()
    {
        return $this->setupFinder()->name('bower.json');
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

        if (isset($bowerJson['authors']) && \is_array($bowerJson['authors'])) {
            return [];
        }

        $mentionedAuthors = \array_map(
            function ($author) {
                if (\is_string($author)) {
                    return $author;
                }

                if (isset($author['email'])) {
                    return \sprintf(
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
        $json['authors'] = [];
        foreach ($authors as $author) {
            $json['authors'][] = $author;
        }

        return $json;
    }
}
