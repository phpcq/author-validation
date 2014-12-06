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
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  Contao Community Alliance <https://c-c-a.org>
 * @link       https://github.com/contao-community-alliance/build-system-tool-author-validation
 * @license    https://github.com/contao-community-alliance/build-system-tool-author-validation/blob/master/LICENSE MIT
 * @filesource
 */

namespace ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Command;

use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor;
use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor\GitAuthorExtractor;
use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor\PhpDocAuthorExtractor;
use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorListComparator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

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
            ->setName('ccabs:tools:check-author')
            ->setDescription('Check that all authors are mentioned in each file.')
            ->addOption(
                'php-files',
                null,
                InputOption::VALUE_NONE,
                'Validate @author annotations in PHP files'
            )
            ->addOption(
                'composer',
                null,
                InputOption::VALUE_NONE,
                'Validate authors in composer.json'
            )
            ->addOption(
                'bower',
                null,
                InputOption::VALUE_NONE,
                'Validate authors in bower.json'
            )
            ->addOption(
                'packages',
                null,
                InputOption::VALUE_NONE,
                'Validate authors in packages.json'
            )
            ->addOption(
                'ignore',
                null,
                (InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
                'authors to ignore (format: "John Doe <j.doe@acme.org>"',
                array()
            )
            ->addArgument(
                'dir',
                InputArgument::OPTIONAL,
                'The directory to start searching, must be a git repository or a subdir in a git repository.',
                '.'
            );
    }

    /**
     * Find PHP files, read the authors and validate against the git log of each file.
     *
     * @param string          $dir     The directory to search files in.
     *
     * @param string[]        $ignores The authors to be ignored from the git repository.
     *
     * @param OutputInterface $output  The output.
     *
     * @return bool
     */
    private function validatePhpAuthors($dir, $ignores, OutputInterface $output)
    {
        $finder = new Finder();

        $finder->in($dir)->notPath('/vendor/')->files()->name('*.php');

        $invalidates = false;
        $comparator  = new AuthorListComparator($output);

        /** @var \SplFileInfo[] $finder */
        foreach ($finder as $file) {
            /** @var \SplFileInfo $file */
            $phpExtractor = new PhpDocAuthorExtractor($file->getPathname(), $output);
            $gitExtractor = new GitAuthorExtractor($file->getPathname(), $output);
            $gitExtractor->setIgnoredAuthors($ignores);

            $invalidates = !$comparator->compare($phpExtractor, $gitExtractor) || $invalidates;
        }

        return !$invalidates;
    }

    /**
     * Create all source extractors as specified on the command line.
     *
     * @param InputInterface  $input  The input interface.
     *
     * @param OutputInterface $output The output interface to use for logging.
     *
     * @param string          $dir    The base directory.
     *
     * @return AuthorExtractor[]
     */
    protected function createSourceExtractors(InputInterface $input, OutputInterface $output, $dir)
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
                 ) as $option => $class) {
            if ($input->getOption($option)) {
                $extractors[$option] = new $class($dir, $output);
            }
        }

        return $extractors;
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ignores    = $input->getOption('ignore');
        $dir        = realpath($input->getArgument('dir'));
        $error      = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $extractors = $this->createSourceExtractors($input, $error, $dir);

        if (empty($extractors)) {
            $error->writeln('<error>You must select at least one validation to run!</error>');
            $error->writeln('validate-author.php [--php-files] [--composer] [--bower] [--packages]');

            return 1;
        }

        $failed = false;

        $gitExtractor = new GitAuthorExtractor($dir, $error);
        $gitExtractor->setIgnoredAuthors($ignores);

        $comparator = new AuthorListComparator($error);

        foreach ($extractors as $extractor) {
            $failed = !$comparator->compare($extractor, $gitExtractor) || $failed;
        }

        // Finally check the php files.

        $failed = ($input->getOption('php-files') && !$this->validatePhpAuthors($dir, $ignores, $error)) || $failed;

        return $failed ? 1 : 0;
    }
}
