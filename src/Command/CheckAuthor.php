<?php

/**
 * This file is part of phpcq/author-validation.
 *
 * (c) Contao Community Alliance <https://c-c-a.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    phpcq/author-validation
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @copyright  Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan.lins@bit3.de>
 * @link       https://github.com/phpcq/author-validation
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation\Command;

use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor;
use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor\GitAuthorExtractor;
use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorListComparator;
use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class to check the mentioned authors.
 *
 * @package ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Command
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
                InputOption::VALUE_NONE,
                'Validate authors in packages.json.'
            )
            ->addOption(
                'ignore',
                null,
                (InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
                'Author to ignore (format: "John Doe <j.doe@acme.org>".',
                array()
            )
            ->addOption(
                'exclude',
                null,
                (InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
                'Path to exclude.',
                array()
            )
            ->addOption(
                'diff',
                null,
                InputOption::VALUE_NONE,
                'Create output in diff format instead of mentioning what\'s missing/superfluous.'
            )
            ->addArgument(
                'include',
                (InputArgument::IS_ARRAY | InputArgument::OPTIONAL),
                'The directory to start searching, must be a git repository or a sub dir in a git repository.',
                array('.')
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
        $extractors = array();
        foreach (array(
            'bower' =>
                'ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor\BowerAuthorExtractor',
            'composer' =>
                'ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor\ComposerAuthorExtractor',
            'packages' =>
                'ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor\NodeAuthorExtractor',
            'php-files' =>
                'ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor\PhpDocAuthorExtractor',
        ) as $option => $class) {
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

        $configFile    = $input->getOption('config');
        $defaultConfig = getcwd() . '/.check-author.yml';
        if (!$configFile && is_file($defaultConfig)) {
            $configFile = $defaultConfig;
        }

        $config = new Config($configFile);
        $config
            ->ignoreAuthors($input->getOption('ignore'))
            ->excludePaths($input->getOption('exclude'))
            ->includePaths(
                array_filter(array_map(
                    function ($arg) {
                        return realpath($arg);
                    },
                    $input->getArgument('include')
                ))
            );

        $diff         = $input->getOption('diff');
        $extractors   = $this->createSourceExtractors($input, $error, $config);
        $gitExtractor = new GitAuthorExtractor($config, $error);
        $comparator   = new AuthorListComparator($config, $error);
        $comparator->shallGeneratePatches($diff);

        $failed = $this->handleExtractors($extractors, $gitExtractor, $comparator);

        if ($failed && $diff) {
            $output->writeln($comparator->getPatchSet());
        }

        return $failed ? 1 : 0;
    }
}
