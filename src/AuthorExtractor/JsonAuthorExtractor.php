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

/**
 * Abstract class for author extraction.
 */
abstract class JsonAuthorExtractor extends AbstractAuthorExtractor
{
    /**
     * Read the composer.json file and return it as array.
     */
    protected function loadFile()
    {
        $pathname = $this->getFilePath();

        if (!is_file($pathname)) {
            return null;
        }

        $composerJson = file_get_contents($pathname);
        return (array) json_decode($composerJson, true);
    }
}
