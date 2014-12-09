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
 * @author     Christan Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  Contao Community Alliance <https://c-c-a.org>
 * @link       https://github.com/contao-community-alliance/build-system-tool-author-validation
 * @license    https://github.com/contao-community-alliance/build-system-tool-author-validation/blob/master/LICENSE MIT
 * @filesource
 */

namespace ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extract the author information from a phpDoc file doc block.
 */
class PhpDocAuthorExtractor extends AbstractPatchingAuthorExtractor
{
    /**
     * The file to work on.
     *
     * @var string
     */
    protected $filePath;

    /**
     * Create a new instance.
     *
     * @param string          $baseDir  The base directory this extractor shall operate within.
     *
     * @param string          $filePath The file to work on.
     *
     * @param OutputInterface $output   The output interface to use for logging.
     */
    public function __construct($baseDir, $filePath, OutputInterface $output)
    {
        parent::__construct($baseDir, $output);

        $this->filePath = $filePath;
    }

    /**
     * Retrieve the file path to use in reporting.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Read the composer.json, if it exists and extract the authors.
     *
     * @return string[]|null
     */
    protected function doExtract()
    {
        if (!preg_match_all('/.*@author\s+(.*)\s*/', $this->getBuffer(), $matches, PREG_OFFSET_CAPTURE)) {
            return array();
        }

        $mentionedAuthors = array();
        foreach ($matches[1] as $match) {
            $mentionedAuthors[] = $match[0];
        }

        return $mentionedAuthors;
    }

    /**
     * Update author list in the storage with the given authors.
     *
     * @param string $authors The author list that shall be used in the resulting buffer (optional, if empty the buffer
     *                        is unchanged).
     *
     * @return string The new storage content with the updated author list.
     */
    public function getBuffer($authors = null)
    {
        $pathname = $this->getFilePath();

        if (!is_file($pathname)) {
            return '';
        }

        // 4k ought to be enough of a file header for anyone (I hope).
        $content = file_get_contents($pathname, null, null, null, 4096);
        $closing = strpos($content, '*/');
        if ($closing === false) {
            return array();
        }

        $tokens    = token_get_all(substr($content, 0, ($closing + 2)));
        $docBlocks = array_filter(
            $tokens,
            function ($item) {
                return $item[0] == T_DOC_COMMENT;
            }
        );
        $firstDocBlock = reset($docBlocks);

        $docBlock = substr($content, 0, $closing+2);

        if ($authors)
        {
            return $this->setAuthors($docBlock, $authors);
        }

        return $docBlock;
    }

    /**
     * Set the author information in doc block.
     *
     * @param array $docBlock    The doc block.
     *
     * @param array $authors The authors to set in the doc block.
     *
     * @return array The updated doc block.
     */
    protected function setAuthors($docBlock, $authors)
    {
        $newAuthors = $this->calculateUpdatedAuthors($authors);
        $lines      = explode("\n", $docBlock);
        $lastAuthor = 0;
        $indention  = ' * @author     ';
        $cleaned    = array();

        foreach ($lines as $number => $line) {
            if (strpos($line, '@author') === false) {
                continue;
            }
            $lastAuthor = $number;
            $suffix     = trim(substr($line, strpos($line, '@author') + 7));
            $indention  = substr($line, 0, strlen($line) - strlen($suffix));

            $found = false;
            foreach ($newAuthors as $newIndex => $author) {
                list($name, $email) = explode(' <', $author);
                $name  = trim($name);
                $email = trim(substr($email, 0, -1));
                if ((strpos($line, $name) !== false) && (strpos($line, $email) !== false)) {
                    $found = true;
                    unset($newAuthors[$newIndex]);
                    break;
                }
            }

            // Obsolete entry, remove it.
            if (!$found) {
                $lines[$number] = null;
                $cleaned[]      = $number;
            }
        }

        if (!empty($newAuthors)) {
            // Fill the gaps we just made.
            foreach ($cleaned as $number) {
                $lines[$number] = $indention . array_shift($newAuthors);
            }

            if ($lastAuthor == 0) {
                $lastAuthor = count($lines) - 2;
            }
            while ($author = array_shift($newAuthors)) {
                $lines[$lastAuthor++] = $indention . $author;
            }
        }

        return implode("\n", array_filter($lines));
    }
}
