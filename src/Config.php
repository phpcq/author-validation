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
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2018 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation;

use Symfony\Component\Yaml\Yaml;

/**
 * Configuration class that reads the .check-authors.yml file.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Config
{
    /**
     * Author mapping.
     *
     * Format:
     *   Alias => Real author
     *
     * @var array
     */
    protected $mapping = [];

    /**
     * List of authors to be ignored.
     *
     * @var array
     */
    protected $ignoredAuthors = [];

    /**
     * List of copy-left authors.
     *
     * Format
     *   Author: [Files obtained from that author]
     *
     * @var array
     */
    protected $copyLeft = [];

    /**
     * List of copy-left authors.
     *
     * Format
     *   Author in array key notation: Author
     *
     * @var array
     */
    protected $copyLeftReal = [];

    /**
     * List of paths to include.
     *
     * @var array
     */
    protected $include = [];

    /**
     * List of paths to exclude.
     *
     * @var array
     */
    protected $exclude = [];

    /**
     * Author metadata.
     *
     * Format
     *   Author: [ 'name' => 'value', .. ]
     *
     * @var array
     */
    protected $metadata = [];

    /**
     * Create a new instance.
     *
     * @param string|bool $configFileName The config file to use.
     */
    public function __construct($configFileName = false)
    {
        if ($configFileName !== false) {
            $this->addFromYml($configFileName);
        }
    }

    /**
     * Translate an author to a key value to be used in the lookup arrays.
     *
     * @param string $author The author to make a key.
     *
     * @return string
     */
    private function arrayKey($author)
    {
        return \strtolower(\trim($author));
    }

    /**
     * Match the passed path name against the pattern.
     *
     * @param string $pathName The path name to match.
     * @param string $pattern  The pattern.
     *
     * @return bool True if the pattern matches, false otherwise.
     */
    private function matchPattern($pathName, $pattern)
    {
        if ($pattern[0] !== '/') {
            $pattern = '**/' . $pattern;
        }

        if (\fnmatch($pattern, $pathName)) {
            return true;
        }

        return false;
    }

    /**
     * Match the passed path name against the pattern list.
     *
     * @param string $pathName    The path name to match.
     * @param array  $patternList The pattern list.
     *
     * @return bool|string The first matching pattern if any of the pattern matches, false otherwise.
     */
    private function matchPatterns($pathName, $patternList)
    {
        foreach ($patternList as $pattern) {
            if ($this->matchPattern($pathName, $pattern)) {
                return $pattern;
            }
        }

        return false;
    }

    /**
     * Retrieve the configuration file and merge it into the instance.
     *
     * @param string $fileName The filename to read.
     *
     * @return Config
     *
     * @throws \InvalidArgumentException When the config is not readable.
     */
    public function addFromYml($fileName)
    {
        if (!\is_readable($fileName)) {
            throw new \InvalidArgumentException('Could not read config file: ' . $fileName);
        }

        $config = Yaml::parse(\file_get_contents($fileName));

        if (isset($config['mapping'])) {
            $this->addAuthorMap($config['mapping']);
        }

        if (isset($config['ignore'])) {
            $this->ignoreAuthors($config['ignore']);
        }

        if (isset($config['copy-left'])) {
            $this->addCopyLeftAuthors($config['copy-left']);
        }

        if (isset($config['include'])) {
            $this->includePaths($config['include']);
        }

        if (isset($config['exclude'])) {
            $this->excludePaths($config['exclude']);
        }

        if (isset($config['metadata'])) {
            $this->addAuthorsMetadata($config['metadata']);
        }

        return $this;
    }

    /**
     * Add an author alias.
     *
     * @param string $alias      The alias for the author.
     * @param string $realAuthor The real author to be used.
     *
     * @return Config
     */
    public function aliasAuthor($alias, $realAuthor)
    {
        $this->mapping[$this->arrayKey($alias)] = trim($realAuthor);

        return $this;
    }

    /**
     * Absorb the author mapping.
     *
     * The input argument must be in the format:
     *   Real author: [multiple aliases]
     * or
     *   Real author: alias
     *
     * @param array $mapping The author mapping to absorb.
     *
     * @return Config
     */
    public function addAuthorMap($mapping)
    {
        foreach ($mapping as $author => $aliases) {
            if (\is_array($aliases)) {
                foreach ($aliases as $alias) {
                    $this->aliasAuthor($alias, $author);
                }
            } else {
                $this->aliasAuthor($aliases, $author);
            }
        }

        return $this;
    }

    /**
     * Check if an author is aliased.
     *
     * @param string $potentialAlias The author to check.
     *
     * @return bool
     */
    public function isAlias($potentialAlias)
    {
        return isset($this->mapping[$this->arrayKey($potentialAlias)]);
    }

    /**
     * Translate an author alias to the real name but return null if the author shall be ignored.
     *
     * @param string $author The author to translate.
     *
     * @return string|null
     */
    public function getRealAuthor($author)
    {
        if ($this->isAuthorIgnored($author)) {
            return null;
        }

        if ($this->isAlias($author)) {
            $author = $this->mapping[$this->arrayKey($author)];
        }

        if ($this->isAuthorIgnored($author)) {
            return null;
        }

        return $author;
    }

    /**
     * Ignore the given author.
     *
     * @param string $author The author to ignore.
     *
     * @return Config
     */
    public function ignoreAuthor($author)
    {
        $this->ignoredAuthors[$this->arrayKey($author)] = \trim($author);

        return $this;
    }

    /**
     * Ignore the given authors.
     *
     * @param array $authors The authors to ignore.
     *
     * @return Config
     */
    public function ignoreAuthors($authors)
    {
        foreach ((array) $authors as $author) {
            $this->ignoreAuthor($author);
        }

        return $this;
    }

    /**
     * Check if an author is aliased.
     *
     * @param string $potentialIgnoredAuthor The author to check.
     *
     * @return bool
     */
    public function isAuthorIgnored($potentialIgnoredAuthor)
    {
        return isset($this->ignoredAuthors[$this->arrayKey($potentialIgnoredAuthor)]);
    }

    /**
     * Add the the given authors to the copy-left list using the given pattern.
     *
     * @param string       $author  The author to add.
     * @param string|array $pattern The pattern to add to the author.
     *
     * @return Config
     */
    public function addCopyLeft($author, $pattern)
    {
        if (\is_array($pattern)) {
            foreach ($pattern as $singlePattern) {
                $this->addCopyLeft($author, $singlePattern);
            }

            return $this;
        }

        $this->copyLeft[$this->arrayKey($author)][$this->arrayKey($pattern)] = $pattern;
        $this->copyLeftReal[$this->arrayKey($author)]                        = $author;

        return $this;
    }

    /**
     * Add the the given authors to the copy-left list.
     *
     * @param array $authors The authors to add.
     *
     * @return Config
     */
    public function addCopyLeftAuthors($authors)
    {
        foreach ($authors as $author => $pattern) {
            $this->addCopyLeft($author, $pattern);
        }

        return $this;
    }

    /**
     * Check if an author is listed as copy-left contributor.
     *
     * @param string $author   The author to check.
     * @param string $pathName The path to check.
     *
     * @return bool
     */
    public function isCopyLeftAuthor($author, $pathName)
    {
        $key = $this->arrayKey($author);
        if (!isset($this->copyLeft[$key])) {
            return false;
        }

        return (bool) $this->matchPatterns($pathName, $this->copyLeft[$key]);
    }

    /**
     * Obtain authors to be listed as copy-left contributor.
     *
     * @param string $pathName The path to check.
     *
     * @return string[]
     */
    public function getCopyLeftAuthors($pathName)
    {
        $result = [];
        foreach ($this->copyLeft as $author => $paths) {
            if ($this->matchPatterns($pathName, $paths)) {
                $realAuthor                           = $this->getRealAuthor($this->copyLeftReal[$author]);
                $result[$this->arrayKey($realAuthor)] = $realAuthor;
            }
        }

        return $result;
    }

    /**
     * Add path to the include list.
     *
     * @param string $path The path to include.
     *
     * @return Config
     */
    public function includePath($path)
    {
        $this->include[$this->arrayKey($path)] = $path;

        return $this;
    }

    /**
     * Add paths to the include list.
     *
     * @param array $paths The paths to include.
     *
     * @return Config
     */
    public function includePaths($paths)
    {
        foreach ((array) $paths as $path) {
            $this->includePath($path);
        }

        return $this;
    }

    /**
     * Check if a path matches the include list.
     *
     * @param string $path The path.
     *
     * @return bool
     */
    public function isPathIncluded($path)
    {
        return $this->matchPatterns($path, $this->include);
    }

    /**
     * Retrieve the list of paths to be included.
     *
     * @return string[]
     */
    public function getIncludedPaths()
    {
        return \array_values($this->include);
    }

    /**
     * Add path to the exclude list.
     *
     * @param string $path The path to exclude.
     *
     * @return Config
     */
    public function excludePath($path)
    {
        $this->exclude[$this->arrayKey($path)] = $path;

        return $this;
    }

    /**
     * Add paths to the exclude list.
     *
     * @param array $paths The paths to exclude.
     *
     * @return Config
     */
    public function excludePaths($paths)
    {
        foreach ((array) $paths as $path) {
            $this->excludePath($path);
        }

        return $this;
    }

    /**
     * Check if a path matches the exclude list.
     *
     * @param string $path The path.
     *
     * @return bool
     */
    public function isPathExcluded($path)
    {
        return $this->matchPatterns($path, $this->exclude);
    }

    /**
     * Retrieve the list of paths to be included.
     *
     * @return string[]
     */
    public function getExcludedPaths()
    {
        return \array_values($this->exclude);
    }

    /**
     * Add authors metadata.
     *
     * Format:
     *   Author: [ name => value, ..]
     *
     * @param array $metadata Authors metadata.
     *
     * @return Config
     */
    public function addAuthorsMetadata($metadata)
    {
        foreach ($metadata as $author => $data) {
            foreach ($data as $name => $value) {
                $this->setMetadata($author, $name, $value);
            }
        }

        return $this;
    }

    /**
     * Check if a specific author metadata is defined.
     *
     * @param string $author The author.
     * @param string $name   Metadata name.
     *
     * @return bool
     */
    public function hasMetadata($author, $name)
    {
        return isset($this->metadata[$this->arrayKey($author)][$name]);
    }

    /**
     * Get a specific meta data for an author.
     *
     * It returns null if it is not set.
     *
     * @param string $author The author.
     * @param string $name   Metadata name.
     *
     * @return mixed
     */
    public function getMetadata($author, $name)
    {
        $author = $this->arrayKey($author);

        if (isset($this->metadata[$author][$name])) {
            return $this->metadata[$author][$name];
        }

        return null;
    }

    /**
     * Set a specific meta data for an author.
     *
     * @param string $author The author.
     * @param string $name   Metadata name.
     * @param mixed  $value  Metadata value.
     *
     * @return Config
     */
    public function setMetadata($author, $name, $value)
    {
        $this->metadata[$this->arrayKey($author)][$name] = $value;

        return $this;
    }
}
