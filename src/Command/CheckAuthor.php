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

namespace PhpCodeQuality\AuthorValidation\Command;

use Bit3\GitPhp\GitRepository as GitPhpRepository;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\AuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\BowerAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\ComposerAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\GitAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\GitProjectAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\GitTypeAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\NodeAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\PhpDocAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorListComparator;
use PhpCodeQuality\AuthorValidation\Config;
use PhpCodeQuality\AuthorValidation\Repository\GitRepository;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function func_get_arg;
use function getenv;
use function is_dir;
use function is_file;
use function posix_isatty;
use function realpath;
use function rtrim;
use function sprintf;
use function strlen;
use function sys_get_temp_dir;

/**
 * Class to check the mentioned authors.
 *
 * @package PhpCodeQuality\AuthorValidation\Command
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class CheckAuthor extends Command
{
    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function configure(): void
    {
        $this
            ->setName('phpcq:check-author')
            ->setDescription('Check that all authors are mentioned in each file.')
            ->addArgument(
                'include',
                InputArgument::OPTIONAL,
                'The directory to start searching, must be a git repository or a sub dir in a git repository.',
                '.'
            )
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
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        if (false === $this->isValidationSelected($input)) {
            $error->writeln('<error>You must select at least one validation to run!</error>');
            $error->writeln('check-author.php [--php-files] [--composer] [--bower] [--packages]');

            return 1;
        }

        $config       = $this->createConfig($input);
        $git          = $this->createGit($output, $config);
        $cache        = $this->createCache($input, $output);
        $diff         = $input->getOption('diff');
        $extractors   = $this->createSourceExtractors($input, $error, $config);
        $gitExtractor = $this->createGitAuthorExtractor($input->getOption('scope'), $config, $error, $cache, $git);
        $progressBar  = !$output->isQuiet() && !$input->getOption('no-progress') && posix_isatty(STDOUT);
        $comparator   = new AuthorListComparator($config, $error, $progressBar);
        $comparator->shallGeneratePatches($diff);

        if ($gitExtractor->repository()->hasUncommittedChanges()) {
            $error->writeln('<error>The git repository has uncommitted changes.</error>');

            return 1;
        }

        $failed = $this->handleExtractors($extractors, $gitExtractor, $comparator);

        if ($failed && $diff) {
            $output->writeln($comparator->getPatchSet());
        }

        return $failed ? 1 : 0;
    }

    /**
     * Check for is a validation selected.
     *
     * @param InputInterface $input The console input.
     *
     * @return bool
     */
    private function isValidationSelected(InputInterface $input): bool
    {
        return ($input->getOption('php-files')
                || $input->getOption('composer')
                || $input->getOption('bower')
                || $input->getOption('packages'));
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
    private function handleExtractors(
        array $extractors,
        AuthorExtractor $reference,
        AuthorListComparator $comparator
    ): bool {
        $failed = false;

        foreach ($extractors as $extractor) {
            $failed = !$comparator->compare($extractor, $reference) || $failed;
        }

        return $failed;
    }

    /**
     * Create all source extractors as specified on the command line.
     *
     * @param InputInterface  $input     The input interface.
     * @param OutputInterface $output    The output interface to use for logging.
     * @param Config          $config    The configuration.
     *
     * @return AuthorExtractor[]
     */
    private function createSourceExtractors(InputInterface $input, OutputInterface $output, Config $config): array
    {
        $options = [
            'bower'     => BowerAuthorExtractor::class,
            'composer'  => ComposerAuthorExtractor::class,
            'packages'  => NodeAuthorExtractor::class,
            'php-files' => PhpDocAuthorExtractor::class,
        ];
        // Remark: a plugin system would be really nice here, so others could simply hook themselves into the checking.
        $extractors = [];
        foreach ($options as $option => $class) {
            if ($input->getOption($option)) {
                $extractors[$option] = new $class($config, $output);
            }
        }

        return $extractors;
    }

    /**
     * Create git author extractor for demanded scope.
     *
     * @param string           $scope  Git author scope.
     * @param Config           $config Author extractor config.
     * @param OutputInterface  $error  Error output.
     * @param CacheInterface   $cache  The cache.
     * @param GitPhpRepository $git    The git repository.
     *
     * @return GitTypeAuthorExtractor
     */
    private function createGitAuthorExtractor(
        string $scope,
        Config $config,
        OutputInterface $error,
        CacheInterface $cache,
        GitPhpRepository $git
    ): GitTypeAuthorExtractor {
        if ('project' === $scope) {
            $extractor = new GitProjectAuthorExtractor($config, $error);
            $extractor->setRepository(new GitRepository($git, $config, $cache));

            return $extractor;
        }

        $extractor = new GitAuthorExtractor($config, $error);
        $extractor->setRepository(new GitRepository($git, $config, $cache));

        $extractor->repository()->analyze();

        return $extractor;
    }

    /**
     * Create the config.
     *
     * @param InputInterface $input The console input.
     *
     * @return Config
     */
    private function createConfig(InputInterface $input): Config
    {
        $config = new Config();

        if (!$input->getOption('do-not-ignore-well-known-bots')) {
            $configFile = dirname(__DIR__, 2)
                          . DIRECTORY_SEPARATOR . 'defaults'
                          . DIRECTORY_SEPARATOR . 'ignore-well-known-bots.yml';
            $config->addFromYml($configFile);
        }

        $configFile = $input->getOption('config');
        if (is_file($configFile)) {
            $config->addFromYml($configFile);
        }

        $config
            ->ignoreAuthors($input->getOption('ignore'))
            ->excludePaths($input->getOption('exclude'))
            ->includePath(realpath($input->getArgument('include')));

        return $config;
    }

    /**
     * Create git.
     *
     * @param OutputInterface $output The console output.
     * @param Config          $config The config.
     *
     * @return GitPhpRepository
     */
    private function createGit(OutputInterface $output, Config $config): GitPhpRepository
    {
        $git = new GitPhpRepository($this->determineGitRoot($config->getIncludedPath()));
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $git->getConfig()->setLogger(
                new ConsoleLogger($output)
            );
        }

        return $git;
    }

    /**
     * Create the cache.
     *
     * @param InputInterface  $input  The console input.
     * @param OutputInterface $output The console output.
     *
     * @return CacheInterface
     */
    private function createCache(InputInterface $input, OutputInterface $output): CacheInterface
    {
        $cacheDir = rtrim((($tmpDir = ('\\' === PATH_SEPARATOR
            ? getenv('HOMEDRIVE') . getenv('HOMEPATH')
            : getenv('HOME'))) ? $tmpDir : sys_get_temp_dir()), '\\/');
        if ($input->getOption('cache-dir')) {
            $cacheDir = rtrim($input->getOption('cache-dir'), '/');
        }
        $cacheDir .= '/.cache/phpcq-author-validation';

        if ($output->isVerbose()) {
            $output->writeln(sprintf('<info>The folder "%s" is used as cache directory.</info>', $cacheDir));
        }

        return new Psr16Cache(
            $input->getOption('no-cache')
                ? new ArrayAdapter()
                : new FilesystemAdapter('phpcq.author-validation', 0, $cacheDir)
        );
    }

    /**
     * Determine the git root, starting from arbitrary directory.
     *
     * @param string $path The start path.
     *
     * @return string The git root path.
     *
     * @throws RuntimeException If the git root could not determined.
     */
    private function determineGitRoot(string $path): string
    {
        // @codingStandardsIgnoreStart
        while (strlen($path) > 1) {
            // @codingStandardsIgnoreEnd
            if (is_dir($path . DIRECTORY_SEPARATOR . '.git')) {
                return $path;
            }

            $path = dirname($path);
        }

        throw new RuntimeException('Could not determine git root, starting from ' . func_get_arg(0));
    }
}
