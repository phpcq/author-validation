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

use function array_filter;
use function array_shift;
use function array_slice;
use function count;
use function explode;
use function file_get_contents;
use function implode;
use function is_file;
use function preg_match_all;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * Extract the author information from a phpDoc file doc block.
 */
final class PhpDocAuthorExtractor implements AuthorExtractor, PatchingExtractor
{
    use AuthorExtractorTrait;
    use PatchingAuthorExtractorTrait;

    /**
     * {@inheritDoc}
     */
    public function getBuffer(string $path, ?array $authors = null): ?string
    {
        if (!is_file($path)) {
            return '';
        }

        // 4k ought to be enough of a file header for anyone (I hope).
        $content = file_get_contents($path, false, null, 0, 4096);
        $closing = strpos($content, '*/');
        if (false === $closing) {
            return '';
        }

        $docBlock = substr($content, 0, ($closing + 3));

        if ($authors) {
            return $this->setAuthors($docBlock, $this->calculateUpdatedAuthors($path, $authors));
        }

        return $docBlock;
    }

    /**
     * {@inheritDoc}
     */
    protected function buildFinder(): Finder
    {
        return $this->setupFinder()->name('*.php');
    }

    /**
     * {@inheritDoc}
     */
    protected function doExtract(string $path): array
    {
        if (!preg_match_all('/.*@author\s+(.*)\s*/', $this->getBuffer($path), $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $mentionedAuthors = [];
        foreach ((array) $matches[1] as $match) {
            $mentionedAuthors[] = $match[0];
        }

        return $mentionedAuthors;
    }

    /**
     * Set the author information in doc block.
     *
     * @param string $docBlock The doc block.
     * @param array  $authors  The authors to set in the doc block.
     *
     * @return string The updated doc block.
     */
    private function setAuthors(string $docBlock, array $authors): string
    {
        $newAuthors = array_unique(array_values($authors));
        $lines      = explode("\n", $docBlock);
        $lastAuthor = 0;
        $indention  = ' * @author     ';
        $cleaned    = [];

        foreach ($lines as $number => $line) {
            if (false === strpos($line, '@author')) {
                continue;
            }
            $lastAuthor = $number;
            $suffix     = trim(substr($line, (strpos($line, '@author') + 7)));
            $indention  = substr($line, 0, (strlen($line) - strlen($suffix)));

            $index = $this->searchAuthor($line, $newAuthors);

            // Obsolete entry, remove it.
            if (null === $index) {
                $lines[$number] = null;
                $cleaned[]      = $number;

                continue;
            }

            unset($newAuthors[$index]);
        }

        $lines = $this->addNewAuthors($lines, $newAuthors, $cleaned, $lastAuthor, $indention);

        return implode(
            "\n",
            array_filter(
                $lines,
                static function ($value) {
                    return null !== $value;
                }
            )
        );
    }

    /**
     * Add new authors to a buffer.
     *
     * @param array  $lines      The buffer to update.
     * @param array  $newAuthors The new authors to add.
     * @param array  $emptyLines The empty line numbers.
     * @param int    $lastAuthor The index in the buffer where the last author annotation is.
     * @param string $indention  The annotation prefix.
     *
     * @return array
     */
    private function addNewAuthors(
        array $lines,
        array $newAuthors,
        array $emptyLines,
        int $lastAuthor,
        string $indention
    ): array {
        if (empty($newAuthors)) {
            return $lines;
        }

        // Fill the gaps we just made.
        foreach ($emptyLines as $number) {
            if (null === ($author = array_shift($newAuthors))) {
                break;
            }
            $lines[$number] = $indention . $author;
        }

        if (0 === $lastAuthor) {
            $lastAuthor = (count($lines) - 2);
        }
        if (0 === ($count = count($newAuthors))) {
            return $lines;
        }
        // Still not empty, we have mooooore.
        $lines = array_merge(
            array_slice($lines, 0, ++$lastAuthor),
            array_fill(0, $count, null),
            array_slice($lines, $lastAuthor)
        );

        while ($author = array_shift($newAuthors)) {
            $lines[$lastAuthor++] = $indention . $author;
        }

        return $lines;
    }

    /**
     * Search the author in "line" in the passed array and return the index of the match or null if none matches.
     *
     * @param string   $line    The author to search for.
     * @param string[] $authors The author list to search in.
     *
     * @return ?int
     */
    private function searchAuthor(string $line, array $authors): ?int
    {
        foreach ($authors as $index => $author) {
            [$name, $email] = explode(' <', $author);

            $name  = trim($name);
            $email = trim(substr($email, 0, -1));
            if ((false !== strpos($line, $name)) && (false !== strpos($line, $email))) {
                unset($authors[$index]);
                return $index;
            }
        }

        return null;
    }
}
