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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract class for author extraction.
 */
abstract class AbstractAuthorExtractor implements AuthorExtractor
{
    /**
     * The base directory to operate within.
     *
     * @var string
     */
    protected $baseDir;

    /**
     * The output to use for logging.
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * The list of ignored authors.
     *
     * @var string
     */
    protected $ignoredAuthors;

    /**
     * The cached result of calls to extract.
     *
     * @var string
     */
    protected $cachedResult;

    /**
     * Create a new instance.
     *
     * @param string          $baseDir The base directory this extractor shall operate within.
     *
     * @param OutputInterface $output  The output interface to use for logging.
     */
    public function __construct($baseDir, OutputInterface $output)
    {
        $this->baseDir = $baseDir;
        $this->output  = $output;
    }

    /**
     * Retrieve the base dir of this extractor.
     *
     * @return string
     */
    public function getBaseDir()
    {
        return $this->baseDir;
    }

    /**
     * Set the authors to be ignored.
     *
     * @param array $ignoredAuthors The authors to be ignored.
     *
     * @return AbstractAuthorExtractor
     */
    public function setIgnoredAuthors(array $ignoredAuthors)
    {
        $this->ignoredAuthors = $this->beautifyAuthorList($ignoredAuthors);

        return $this;
    }

    /**
     * Get the authors to be ignored.
     *
     * @return string[]
     */
    public function getIgnoredAuthors()
    {
        return $this->ignoredAuthors ? $this->ignoredAuthors : array();
    }

    /**
     * Retrieve the contained authors.
     *
     * @return string[]
     */
    public function extractAuthors()
    {
        if (!$this->cachedResult) {
            $result = $this->beautifyAuthorList($this->doExtract());
            if (is_array($result)) {
                $result = array_diff_key(
                    $result,
                    $this->getIgnoredAuthors()
                );
            }

            $this->cachedResult = $result;
        }

        return $this->cachedResult;
    }

    /**
     * Ensure the list is case insensitively unique and that the authors are sorted.
     *
     * @param string[]|null $authors The authors to work on.
     *
     * @return string[] The filtered and sorted list.
     */
    private function beautifyAuthorList($authors)
    {
        if ($authors === null) {
            return null;
        }

        $authors = array_intersect_key($authors, array_unique(array_map('strtolower', $authors)));
        usort($authors, 'strcasecmp');

        $mapped = array();
        foreach ($authors as $author) {
            $mapped[strtolower($author)] = $author;
        }

        return $mapped;
    }

    /**
     * Perform the extraction of authors.
     *
     * @return string[] The author list.
     */
    abstract protected function doExtract();
}
