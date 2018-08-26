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

use PhpCodeQuality\AuthorValidation\AuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorExtractor\GitAuthorExtractor;
use PhpCodeQuality\AuthorValidation\AuthorListComparator;
use PhpCodeQuality\AuthorValidation\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            );
    }

    /**
     * Create all source extractors as specified on the command line.
     *
     * @param InputInterface  $input  The input interface.
     *
     * @param OutputInterface $output The output interface to use for logging.
     *
     * @param Config          $config The configuration.
     *
     * @return AuthorExtractor[]
     */
    protected function createSourceExtractors(InputInterface $input, OutputInterface $output, $config)
    {
        // Remark: a plugin system would be really nice here, so others could simply hook themselves into the checking.
        $extractors = [];
        foreach ([
                'bower'     => 'PhpCodeQuality\AuthorValidation\AuthorExtractor\BowerAuthorExtractor',
                'composer'  => 'PhpCodeQuality\AuthorValidation\AuthorExtractor\ComposerAuthorExtractor',
                'packages'  => 'PhpCodeQuality\AuthorValidation\AuthorExtractor\NodeAuthorExtractor',
                'php-files' => 'PhpCodeQuality\AuthorValidation\AuthorExtractor\PhpDocAuthorExtractor',
            ] as $option => $class) {
            if ($input->getOption($option)) {
                $extractors[$option] = new $class($config, $output);
            }
        }

        return $extractors;
    }

    /**
     * Process the given extractors.
     *
     * @param AuthorExtractor[]    $extractors The extractors.
     *
     * @param AuthorExtractor      $reference  The extractor to use as reference.
     *
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
                \array_filter(\array_map(
                    function ($arg) {
                        return \realpath($arg);
                    },
                    $input->getArgument('include')
                ))
            );

        $diff         = $input->getOption('diff');
        $extractors   = $this->createSourceExtractors($input, $error, $config);
        $gitExtractor = $this->createGitAuthorExtractor($input->getOption('scope'), $config, $error);
        $comparator   = new AuthorListComparator($config, $error);
        $comparator->shallGeneratePatches($diff);

        $failed = $this->handleExtractors($extractors, $gitExtractor, $comparator);

        if ($failed && $diff) {
            $output->writeln($comparator->getPatchSet());
        }

        return $failed ? 1 : 0;
    }

    /**
     * Create git author extractor for demanded scope.
     *
     * @param string          $scope  Git author scope.
     * @param Config          $config Author extractor config.
     * @param OutputInterface $error  Error output.
     *
     * @return GitAuthorExtractor|AuthorExtractor\GitProjectAuthorExtractor
     */
    private function createGitAuthorExtractor($scope, Config $config, $error)
    {
        if ($scope === 'project') {
            return new AuthorExtractor\GitProjectAuthorExtractor($config, $error);
        } else {
            return new GitAuthorExtractor($config, $error);
        }
    }
}
