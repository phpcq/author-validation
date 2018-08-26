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
 * @author     Konstantin Kudryashiv <ever.zet@gmail.com>
 * @author     Jordi Boggiano <j.boggiano@seld.be>
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Tristan Lins <tristan@lins.io>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2018 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation\Json;

/**
 * Simple json-encoder and -formatter.
 *
 * Formats json strings used for php < 5.4 because the json_encode doesn't
 * supports the flags JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
 * in these versions
 *
 * @author Konstantin Kudryashiv <ever.zet@gmail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class JsonFormatter
{
    /**
     * Copied and altered from composer.
     *
     * This code is based on the function found at:
     *  http://recursive-design.com/blog/2008/03/11/format-json-with-php/
     *
     * Originally licensed under MIT by Dave Perrett <mail@recursive-design.com>
     *
     * @param array $data The data to encode.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public static function format($data)
    {
        // This is:
        // JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        $json = \json_encode($data, 448);
        // compact brackets to follow recent php versions
        if (PHP_VERSION_ID < 50428
            || (PHP_VERSION_ID >= 50500 && PHP_VERSION_ID < 50512)
            || (\defined('JSON_C_VERSION') && \version_compare(\phpversion('json'), '1.3.6', '<'))
        ) {
            $json = \preg_replace('/\[\s+\]/', '[]', $json);
            $json = \preg_replace('/\{\s+\}/', '{}', $json);
        }

        return $json;
    }
}
