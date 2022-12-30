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
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2022 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

declare(strict_types=1);

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use Symfony\Component\Finder\Finder;

/**
 * Extract the author information from a git repository. It does not care about which file where changed.
 */
final class GitProjectAuthorExtractor implements GitTypeAuthorExtractor
{
    use AuthorExtractorTrait;
    use GitAuthorExtractorTrait;

    public const TYPE = 'project';

    /**
     * Optional attached finder for processing multiple files.
     *
     * @var Finder
     */
    protected Finder $finder;

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * Perform the extraction of authors.
     *
     * @return string[]|null
     */
    protected function doExtract(): ?array
    {
        $authors = $this->repository->convertAuthorList(
            $this->getType(),
            $this->repository->fetchAuthorListFrom($this->getType())
        );

        $authors[] = $this->repository->getCurrentUserInfo();

        return $authors;
    }
}
