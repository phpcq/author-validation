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

namespace PhpCodeQuality\AuthorValidation;

use Diff;
use Diff_Renderer_Abstract;
use Diff_Renderer_Text_Unified;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

use function array_diff_key;
use function array_intersect;
use function count;
use function explode;
use function implode;
use function sprintf;

/**
 * Class for comparing two author lists against each other.
 */
class AuthorListComparator
{
    /**
     * The configuration this extractor shall operate within.
     *
     * @var Config
     */
    protected Config $config;

    /**
     * The output to use for logging.
     *
     * @var OutputInterface
     */
    protected OutputInterface $output;

    /**
     * The diff tool to use.
     *
     * @var Diff_Renderer_Abstract|null
     */
    protected ?Diff_Renderer_Abstract $diff;

    /**
     * The patch file being generated.
     *
     * @var array
     */
    protected array $patchSet;

    /**
     * Use the progress bar.
     *
     * @var bool
     */
    protected bool $useProgressBar;

    /**
     * Create a new instance.
     *
     * @param Config          $config         The configuration this extractor shall operate with.
     * @param OutputInterface $output         The output interface to use for logging.
     * @param bool            $useProgressBar Determine for use the progress bar.
     */
    public function __construct(Config $config, OutputInterface $output, bool $useProgressBar)
    {
        $this->config         = $config;
        $this->output         = $output;
        $this->useProgressBar = $useProgressBar;
    }

    /**
     * Set the flag if diffs shall be generated or not.
     *
     * @param bool $flag The flag to set (optional, default: boolean).
     *
     * @return AuthorListComparator
     */
    public function shallGeneratePatches(bool $flag = true): AuthorListComparator
    {
        $this->diff = $flag ? new Diff_Renderer_Text_Unified() : null;

        return $this;
    }

    /**
     * Retrieve the patch content collected.
     *
     * NOTE: you have to set shallGeneratePatches() first.
     *
     * @return null|array
     */
    public function getPatchSet(): ?array
    {
        return $this->diff ? $this->patchSet : null;
    }

    /**
     * Handle the patching cycle for a extractor.
     *
     * @param string          $path          The path to patch.
     * @param AuthorExtractor $extractor     The extractor to patch.
     * @param array           $wantedAuthors The authors that shall be contained in the result.
     *
     * @return bool True if the patch has been collected, false otherwise.
     */
    private function patchExtractor(string $path, AuthorExtractor $extractor, array $wantedAuthors): bool
    {
        if (!($this->diff && $extractor instanceof PatchingExtractor)) {
            return false;
        }

        $original = explode("\n", $extractor->getBuffer($path));
        $new      = explode("\n", $extractor->getBuffer($path, $wantedAuthors));
        $diff     = new Diff($original, $new);
        $patch    = $diff->render($this->diff);

        if (empty($patch)) {
            return false;
        }

        $patchFile = $path;

        foreach ($this->config->getIncludedPaths() as $prefix) {
            $prefixLength = \strlen($prefix);
            if (strpos($path, $prefix) === 0) {
                $patchFile = \substr($path, $prefixLength);

                if (strncmp($patchFile, '/', 1) === 0) {
                    $patchFile = \substr($patchFile, 1);
                }
                break;
            }
        }

        $this->patchSet[] =
            'diff ' . $patchFile . ' ' . $patchFile . "\n" .
            '--- ' . $patchFile . "\n" .
            '+++ ' . $patchFile . "\n" .
            $patch;

        return true;
    }

    /**
     * Determine the superfluous authors from the passed arrays.
     *
     * @param array  $mentionedAuthors The author list containing the current state.
     * @param array  $wantedAuthors    The author list containing the desired state.
     * @param string $path             The path to relate to.
     *
     * @return array
     */
    private function determineSuperfluous(array $mentionedAuthors, array $wantedAuthors, string $path): array
    {
        $superfluous = [];
        foreach (array_diff_key($mentionedAuthors, $wantedAuthors) as $key => $author) {
            if (!$this->config->isCopyLeftAuthor($author, $path)) {
                $superfluous[$key] = $author;
            }
        }

        return $superfluous;
    }

    /**
     * Run comparison for a given path.
     *
     * @param AuthorExtractor $current     The author list containing the current state.
     * @param AuthorExtractor $should      The author list containing the desired state.
     * @param ProgressBar     $progressBar The progress bar.
     * @param string          $path        The path to compare.
     *
     * @return bool
     */
    private function comparePath(
        AuthorExtractor $current,
        AuthorExtractor $should,
        ProgressBar $progressBar,
        string $path
    ): bool {
        $validates        = true;
        $mentionedAuthors = $current->extractAuthorsFor($path);
        $multipleAuthors  = $current->extractMultipleAuthorsFor($path);
        $wantedAuthors    = array_merge($should->extractAuthorsFor($path), $this->config->getCopyLeftAuthors($path));

        // If current input is not valid, return.
        if ($mentionedAuthors === null) {
            if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $this->output->writeln(
                    sprintf('Skipped check of <info>%s</info> as it is not present.', $path)
                );
            }

            if ($this->useProgressBar) {
                $progressBar->advance(1);
                $progressBar->setMessage('Author validation is in progress...');
            }
            return true;
        }

        $superfluousMentions = $this->determineSuperfluous($mentionedAuthors, $wantedAuthors, $path);
        $missingMentions     = array_diff_key($wantedAuthors, $mentionedAuthors);

        if (count($superfluousMentions)) {
            $this->output->writeln(
                sprintf(
                    PHP_EOL .
                    PHP_EOL .
                    'The file <info>%s</info> is mentioning superfluous author(s):' .
                    PHP_EOL .
                    '<comment>%s</comment>' .
                    PHP_EOL,
                    $path,
                    implode(PHP_EOL, $superfluousMentions)
                )
            );
            $validates = false;
        }

        if (count($missingMentions)) {
            $this->output->writeln(
                sprintf(
                    PHP_EOL .
                    PHP_EOL .
                    'The file <info>%s</info> is not mentioning its author(s):' .
                    PHP_EOL .
                    '<comment>%s</comment>' .
                    PHP_EOL,
                    $path,
                    implode(PHP_EOL, $missingMentions)
                )
            );
            $validates = false;
        }

        if (count($multipleAuthors)) {
            $this->output->writeln(
                sprintf(
                    PHP_EOL .
                    PHP_EOL .
                    'The file <info>%s</info> multiple author(s):' .
                    PHP_EOL .
                    '<comment>%s</comment>' .
                    PHP_EOL,
                    $path,
                    implode(PHP_EOL, $multipleAuthors)
                )
            );

            $validates = false;
        }

        if (!$validates) {
            $this->patchExtractor($path, $current, $wantedAuthors);
        }

        if ($this->useProgressBar) {
            $progressBar->advance(1);
            $progressBar->setMessage('Author validation is in progress...');
        }

        return $validates;
    }

    /**
     * Compare two author lists against each other.
     *
     * This method adds messages to the output if any problems are encountered.
     *
     * @param AuthorExtractor $current The author list containing the current state.
     * @param AuthorExtractor $should  The author list containing the desired state.
     *
     * @return bool
     */
    public function compare(AuthorExtractor $current, AuthorExtractor $should): bool
    {
        $shouldPaths  = $should->getFilePaths();
        $currentPaths = $current->getFilePaths();
        $allPaths     = array_intersect($shouldPaths, $currentPaths);
        $validates    = true;

        $progressBar = new ProgressBar($this->output, count($allPaths));
        if ($this->useProgressBar) {
            $progressBar->start();
            $progressBar->setMessage('Start author validation.');
            $progressBar->setFormat('%current%/%max% [%bar%] %message% %elapsed:6s%');
        }

        foreach ($allPaths as $pathname) {
            $validates = $this->comparePath($current, $should, $progressBar, $pathname) && $validates;
        }

        if ($this->useProgressBar) {
            $progressBar->setMessage('Finished author validation.');
            $progressBar->finish();
            $this->output->writeln(PHP_EOL);
        }

        return $validates;
    }
}
