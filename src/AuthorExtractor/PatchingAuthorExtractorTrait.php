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

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use SplFileInfo;

use function array_intersect_key;
use function array_merge;

/**
 * Trait for author extraction.
 */
trait PatchingAuthorExtractorTrait
{
    /**
     * Calculate the updated author map.
     *
     * The passed authors will be used as new reference, all existing not mentioned anymore will not be contained in
     * the result.
     *
     * @param string $path    A path obtained via a prior call to AuthorExtractor::getFilePaths().
     * @param array  $authors The new author list.
     *
     * @return string[]
     */
    protected function calculateUpdatedAuthors(string $path, array $authors): array
    {
        return array_merge(array_intersect_key($this->extractAuthorsFor($path), $authors), $authors);
    }

    /**
     * {@inheritDoc}
     */
    public function getFilePaths(): array
    {
        $finder = $this->buildFinder();
        $files  = [];

        /** @var SplFileInfo[] $finder */
        foreach ($finder as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }
}
