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

namespace PhpCodeQuality\AuthorValidation\Command;

use Bit3\GitPhp\GitRepository;
use Cache\Adapter\Doctrine\DoctrineCachePool;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\VoidCache;
use PhpCodeQuality\AuthorValidation\AuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\GitAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\GitProjectAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorListComparator;
use PhpCodeQuality\AuthorValidation\Config;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class to check the mentioned authors.
 *
 * @package PhpCodeQuality\AuthorValidation\Command
 */
class CheckAuthor extends Command
{
    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function configure()
    {
        $this
            ->setName('phpcq:check-author')
            ->setDescription('Check that all authors are mentioned in each file.')
            ->addOption(
                'php-files',
                null,
                InputOption::VALUE_NONE,
                'Validate @author annotations in PHP files.'
            )
            ->addOption(
                'composer',
                null,
                InputOption::VALUE_NONE,
                'Validate authors in composer.json.'
            )
            ->addOption(
                'bower',
                null,
                InputOption::VALUE_NONE,
                'Validate authors in bower.json.'
            )
            ->addOption(
                'packages',
                null,
                InputOption::VALUE_NONE,
                'Validate authors in packages.json.'
            )
            ->addOption(
                '--config',
                '-f',
                InputOption::VALUE_REQUIRED,
                'Path to configuration file.',
                '.check-author.yml'
            )
            ->addOption(
                '--do-not-ignore-well-known-bots',
                null,
                InputOption::VALUE_NONE,
                'Skip our ignore-well-known-bots.yml configuration.'
            )
            ->addOption(
                'ignore',
                null,
                (InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
                'Author to ignore (format: "John Doe <j.doe@acme.org>".',
                []
            )
            ->addOption(
                'exclude',
                null,
                (InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
                'Path to exclude.',
                []
            )
            ->addOption(
                'diff',
                null,
                InputOption::VALUE_NONE,
                'Create output in diff format instead of mentioning what\'s missing/superfluous.'
            )
            ->addOption(
                'scope',
                null,
                InputOption::VALUE_OPTIONAL,
                'Determine if file should contain authors of the file or the project',
                'file'
            )
            ->addArgument(
                'include',
                (InputArgument::IS_ARRAY | InputArgument::OPTIONAL),
                'The directory to start searching, must be a git repository or a sub dir in a git repository.',
                ['.']
            )
            ->addOption(
                'no-cache',
                null,
                InputOption::VALUE_NONE,
                'Use this option, if you not use the cache.'
            )
            ->addOption(
                'cache-dir',
                null,
                (InputOption::VALUE_NONE | InputArgument::OPTIONAL),
                'The cache directory do you will use. Is this option not set, then the system temp dir used.'
            )
            ->addOption(
                'no-progress',
                null,
                InputOption::VALUE_NONE,
                'Disable the progress bar output.'
            )
            ->addOption(
                'no-progress-bar',
                null,
                InputOption::VALUE_NONE,
                'Disable the progress bar output. Use --no-progress [DEPRECATED]'
            );
    }

    /**
     * Create all source extractors as specified on the command line.
     *
     * @param InputInterface         $input     The input interface.
     * @param OutputInterface        $output    The output interface to use for logging.
     * @param Config                 $config    The configuration.
     * @param CacheInterface         $cachePool The cache.
     *
     * @return AuthorExtractor[]
     */
    protected function createSourceExtractors(
        InputInterface $input,
        OutputInterface $output,
        $config,
        CacheInterface $cachePool
    ) {
        // Remark: a plugin system would be really nice here, so others could simply hook themselves into the checking.
        $extractors = [];
        foreach ([
                'bower'     => AuthorExtractor\BowerAuthorExtractor::class,
                'composer'  => AuthorExtractor\ComposerAuthorExtractor::class,
                'packages'  => AuthorExtractor\NodeAuthorExtractor::class,
                'php-files' => AuthorExtractor\PhpDocAuthorExtractor::class,
            ] as $option => $class) {
            if ($input->getOption($option)) {
                $extractors[$option] = new $class($config, $output, $cachePool);
            }
        }

        return $extractors;
    }

    /**
     * Process the given extractors.
     *
     * @param AuthorExtractor[]    $extractors The extractors.
     * @param AuthorExtractor      $reference  The extractor to use as reference.
     * @param AuthorListComparator $comparator The comparator to use.
     *
     * @return bool
     */
    private function handleExtractors($extractors, AuthorExtractor $reference, AuthorListComparator $comparator)
    {
        $failed = false;

        foreach ($extractors as $extractor) {
            $failed = !$comparator->compare($extractor, $reference) || $failed;
        }

        return $failed;
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        if (!($input->getOption('php-files')
            || $input->getOption('composer')
            || $input->getOption('bower')
            || $input->getOption('packages'))
        ) {
            $error->writeln('<error>You must select at least one validation to run!</error>');
            $error->writeln('check-author.php [--php-files] [--composer] [--bower] [--packages]');

            return 1;
        }

        $this->enableDeprecatedNoProgressBar($input);

        $config = new Config();

        if (!$input->getOption('do-not-ignore-well-known-bots')) {
            $configFile = \dirname(\dirname(__DIR__))
                . DIRECTORY_SEPARATOR . 'defaults'
                . DIRECTORY_SEPARATOR . 'ignore-well-known-bots.yml';
            $config->addFromYml($configFile);
        }

        $configFile = $input->getOption('config');
        if (\is_file($configFile)) {
            $config->addFromYml($configFile);
        }

        $config
            ->ignoreAuthors($input->getOption('ignore'))
            ->excludePaths($input->getOption('exclude'))
            ->includePaths(
                \array_filter(\array_map('realpath', $input->getArgument('include')))
            );

        $paths = \array_values($config->getIncludedPaths());
        $git   = new GitRepository($this->determineGitRoot($paths[0]));
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $git->getConfig()->setLogger(
                new ConsoleLogger($output)
            );
        }

        $cacheDir = \rtrim((($tmpDir = ('\\' === PATH_SEPARATOR
            ? \getenv('HOMEDRIVE') . \getenv('HOMEPATH')
            : \getenv('HOME'))) ? $tmpDir : \sys_get_temp_dir()), '\\/');
        if ($input->getOption('cache-dir')) {
            $cacheDir = \rtrim($input->getOption('cache-dir'), '/');
        }
        $cacheDir .= '/.cache/phpcq-author-validation';

        if ($output->isVerbose()) {
            $error->writeln(\sprintf('<info>The folder "%s" is used as cache directory.</info>', $cacheDir));
        }

        $cachePool = new DoctrineCachePool(
            $input->getOption('no-cache')
                ? new VoidCache()
                : new FilesystemCache($cacheDir)
        );

        $diff         = $input->getOption('diff');
        $extractors   = $this->createSourceExtractors($input, $error, $config, $cachePool);
        $gitExtractor = $this->createGitAuthorExtractor($input->getOption('scope'), $config, $error, $cachePool, $git);
        $progressBar  = !$output->isQuiet() && !$input->getOption('no-progress') && \posix_isatty(STDOUT);
        $comparator   = new AuthorListComparator($config, $error, $progressBar);
        $comparator->shallGeneratePatches($diff);

        $failed = $this->handleExtractors($extractors, $gitExtractor, $comparator);

        if ($failed && $diff) {
            $output->writeln($comparator->getPatchSet());
        }

        return $failed ? 1 : 0;
    }

    /**
     * Enable the deprecated no progress bar option.
     *
     * @param InputInterface $input The input.
     *
     * @return void
     *
     * @deprecated This is deprecated since 1.4 and where removed in 2.0. Use the option no-progress instead.
     */
    private function enableDeprecatedNoProgressBar(InputInterface $input)
    {
        if (!$input->getOption('no-progress-bar')) {
            return;
        }

        // @codingStandardsIgnoreStart
        @trigger_error(
            'The command option --no-progress-bar is deprecated since 1.4 and where removed in 2.0.
             Use --no-progress instead.',
            E_USER_DEPRECATED
        );
        // @codingStandardsIgnoreEnd

        $input->setOption('no-progress', true);
    }

    /**
     * Create git author extractor for demanded scope.
     *
     * @param string          $scope  Git author scope.
     * @param Config          $config Author extractor config.
     * @param OutputInterface $error  Error output.
     * @param CacheInterface  $cache  The cache.
     * @param GitRepository   $git    The git repository.
     *
     * @return GitAuthorExtractor|GitProjectAuthorExtractor
     */
    private function createGitAuthorExtractor($scope, Config $config, $error, $cache, GitRepository $git)
    {
        if ($scope === 'project') {
            return new GitProjectAuthorExtractor($config, $error, $cache);
        } else {
            $extractor = new GitAuthorExtractor($config, $error, $cache);

            $extractor->collectFilesWithCommits($git);

            return $extractor;
        }
    }

    /**
     * Determine the git root, starting from arbitrary directory.
     *
     * @param string $path The start path.
     *
     * @return string The git root path.
     *
     * @throws \RuntimeException If the git root could not determined.
     */
    private function determineGitRoot($path)
    {
        // @codingStandardsIgnoreStart
        while (\strlen($path) > 1) {
            // @codingStandardsIgnoreEnd
            if (\is_dir($path . DIRECTORY_SEPARATOR . '.git')) {
                return $path;
            }

            $path = \dirname($path);
        }

        throw new \RuntimeException('Could not determine git root, starting from ' . \func_get_arg(0));
    }
}
