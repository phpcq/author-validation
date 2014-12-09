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

namespace ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Json;

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
        if (version_compare(PHP_VERSION, '5.4', '>=')) {
            // This is:
            // JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            $json = json_encode($data, 448);
            //  compact brackets to follow recent php versions
            if (PHP_VERSION_ID < 50428
                || (PHP_VERSION_ID >= 50500 && PHP_VERSION_ID < 50512)
                || (defined('JSON_C_VERSION') && version_compare(phpversion('json'), '1.3.6', '<'))
            ) {
                $json = preg_replace('/\[\s+\]/', '[]', $json);
                $json = preg_replace('/\{\s+\}/', '{}', $json);
            }
            return $json;
        }

        $json = json_encode($data);

        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = '    ';
        $newLine     = "\n";
        $outOfQuotes = true;
        $buffer      = '';
        $noescape    = true;

        for ($i = 0; $i < $strLen; $i++) {
            // Grab the next character in the string
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ('"' === $char && $noescape) {
                $outOfQuotes = !$outOfQuotes;
            }

            if (!$outOfQuotes) {
                $buffer  .= $char;
                $noescape = '\\' === $char ? !$noescape : true;
                continue;
            } elseif ('' !== $buffer) {
                $buffer = str_replace('\\/', '/', $buffer);

                if (function_exists('mb_convert_encoding')) {
                    // See thread at http://stackoverflow.com/questions/2934563
                    $buffer = preg_replace_callback('/(\\\\+)u([0-9a-f]{4})/i', function ($match) {
                        $length = strlen($match[1]);

                        if (($length % 2)) {
                            return str_repeat('\\', ($length - 1)) . mb_convert_encoding(
                                pack('H*', $match[2]),
                                'UTF-8',
                                'UCS-2BE'
                            );
                        }

                        return $match[0];
                    }, $buffer);
                }

                $result .= $buffer.$char;
                $buffer  = '';
                continue;
            }

            if (':' === $char) {
                // Add a space after the : character
                $char .= ' ';
            } elseif (('}' === $char || ']' === $char)) {
                $pos--;
                $prevChar = substr($json, ($i - 1), 1);

                if ('{' !== $prevChar && '[' !== $prevChar) {
                    // If this character is the end of an element,
                    // output a new line and indent the next line
                    $result .= $newLine;
                    for ($j = 0; $j < $pos; $j++) {
                        $result .= $indentStr;
                    }
                } else {
                    // Collapse all empty braces to have shorter output. This applies to {} and [].
                    $result = rtrim($result);
                }
            }

            $result .= $char;

            // If the last character was the beginning of an element,
            // output a new line and indent the next line
            if (',' === $char || '{' === $char || '[' === $char) {
                $result .= $newLine;

                if ('{' === $char || '[' === $char) {
                    $pos++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }
        }

        return $result;
    }
}
