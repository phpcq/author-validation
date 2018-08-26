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
use PhpCodeQuality\AuthorValidation\Config;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Trait for author extraction.
 */
trait AuthorExtractorTrait
{
    /**
     * The configuration this extractor shall operate within.
     *
     * @var Config
     */
    protected $config;

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
     * @var array
     */
    protected $cachedResult = [];

    /**
     * Create a new instance.
     *
     * @param Config          $config The configuration this extractor shall operate with.
     *
     * @param OutputInterface $output The output interface to use for logging.
     */
    public function __construct(Config $config, OutputInterface $output)
    {
        $this->config = $config;
        $this->output = $output;
    }

    /**
     * {@inheritDoc}
     */
    public function extractAuthorsFor($path)
    {
        if (!isset($this->cachedResult[$path])) {
            $result = $this->beautifyAuthorList($this->doExtract($path));
            if (\is_array($result)) {
                $authors = [];
                foreach ($result as $author) {
                    $author = $this->config->getRealAuthor($author);
                    if ($author) {
                        $authors[\strtolower($author)] = $author;
                    }
                }
                $result = $authors;
            }

            $this->cachedResult[$path] = $result;
        }

        return $this->cachedResult[$path];
    }

    /**
     * {@inheritDoc}
     */
    public function extractMultipleAuthorsFor($path)
    {
        $authors = \array_count_values((array) $this->doExtract($path));
        if (!\count($authors)) {
            return [];
        }

        $multipleAuthors = [];
        foreach ($authors as $author => $count) {
            if (2 > $count) {
                continue;
            }

            $multipleAuthors[] = $author . ' count: ' . $count;
        }

        return $multipleAuthors;
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

        $authors = \array_intersect_key($authors, \array_unique(\array_map('strtolower', $authors)));
        \usort($authors, 'strcasecmp');

        $mapped = [];
        foreach ($authors as $author) {
            $mapped[\strtolower($author)] = $author;
        }

        return $mapped;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilePaths()
    {
        $finder = $this->buildFinder();
        $files  = [];

        /** @var \SplFileInfo[] $finder */
        foreach ($finder as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * Perform the extraction of authors.
     *
     * @param string $path A path obtained via a prior call to AuthorExtractorTrait::getFilePaths().
     *
     * @return string[]|null The author list.
     */
    abstract protected function doExtract($path);

    /**
     * Build a Symfony Finder instance that searches all included paths for files.
     *
     * The local config instance will be queried for included and excluded files and the Finder will be populated with
     * them.
     *
     * @return Finder
     */
    protected function buildFinder()
    {
        return $this->setupFinder();
    }

    /**
     * Setup the Symfony Finder.
     *
     * @return Finder
     */
    protected function setupFinder()
    {
        $finder = new Finder();
        $finder
            ->in($this->config->getIncludedPaths())
            ->notPath('/vendor/')
            ->files();
        foreach ($this->config->getExcludedPaths() as $excluded) {
            $finder->notPath($excluded);
        }

        return $finder;
    }
}
