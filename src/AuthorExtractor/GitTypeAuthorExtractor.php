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
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2022 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

declare(strict_types=1);

namespace PhpCodeQuality\AuthorValidation\AuthorExtractor;

use PhpCodeQuality\AuthorValidation\Repository\GitRepository;

/**
 * Interface for typed git author extractor.
 */
interface GitTypeAuthorExtractor extends AuthorExtractor
{
    /**
     * Call the internal git repository.
     *
     * @return GitRepository
     */
    public function repository(): GitRepository;

    /**
     * The type determine if file should contain authors of the file or the project.
     *
     * @return string
     */
    public function getType(): string;
}
