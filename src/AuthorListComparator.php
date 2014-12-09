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

namespace ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class for comparing two author lists against each other.
 */
class AuthorListComparator
{
    /**
     * The output to use for logging.
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * The diff tool to use.
     *
     * @var \Diff_Renderer_Abstract
     */
    protected $diff;

    /**
     * The patch file being generated.
     *
     * @var string
     */
    protected $patchSet;

    /**
     * Create a new instance.
     *
     * @param OutputInterface $output The output interface to use for logging.
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Set the flag if diffs shall be generated or not.
     *
     * @param bool $flag The flag to set (optional, default: boolean).
     *
     * @return AuthorListComparator
     */
    public function shallGeneratePatches($flag = true)
    {
        $this->diff = $flag ? new \Diff_Renderer_Text_Unified() : null;

        return $this;
    }

    /**
     * Retrieve the patch content collected.
     *
     * NOTE: you have to set shallGeneratePatches() first.
     *
     * @return null|string
     */
    public function getPatchSet()
    {
        return $this->diff ? $this->patchSet : null;
    }

    /**
     * Handle the patching cycle for a extractor.
     *
     * @param AuthorExtractor $extractor     The extractor to patch.
     *
     * @param array           $wantedAuthors The authors that shall be contained in the result.
     *
     * @return bool True if the patch has been collected, false otherwise.
     */
    private function patchExtractor($extractor, $wantedAuthors)
    {
        if (!($this->diff && $extractor instanceof PatchingExtractor)) {
            return false;
        }

        $original = explode("\n", $extractor->getBuffer());
        $new      = explode("\n", $extractor->getBuffer($wantedAuthors));
        $diff     = new \Diff($original, $new);
        $patch    = $diff->render($this->diff);

        if (empty($patch)) {
            return false;
        }

        $patchFile = substr($extractor->getFilePath(), strlen($extractor->getBaseDir()));
        if ($patchFile[0] == '/') {
            $patchFile = substr($patchFile, 1);
        }

        /**
         *
         * diff --git a/bin/check-author.php b/bin/check-author.php
         * index 6c031df..75a3d96 100755
         * --- a/bin/check-author.php
         * +++ b/bin/check-author.php
         * @@ -12,7 +12,6 @@
         *
         */

        $this->patchSet[] =
            'diff ' . $patchFile . ' ' .$patchFile . "\n" .
            '--- ' . $patchFile . "\n" .
            '+++ ' . $patchFile . "\n"  . $patch;

        return true;
    }

    /**
     * Compare two author lists against each other.
     *
     * This method adds messages to the output if any problems are encountered.
     *
     * @param AuthorExtractor $current The author list containing the current state.
     *
     * @param AuthorExtractor $should  The author list containing the desired state.
     *
     * @return bool
     */
    public function compare(AuthorExtractor $current, AuthorExtractor $should)
    {
        $validates        = true;
        $mentionedAuthors = $current->extractAuthors();
        $wantedAuthors    = $should->extractAuthors();

        // If current input is not valid, return.
        if ($mentionedAuthors === null) {
            if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $this->output->writeln(
                    sprintf('Skipped check of <info>%s</info> as it is not present.', $current->getFilePath())
                );
            }

            return true;
        }

        $wasteMentions   = array_diff_key($mentionedAuthors, $wantedAuthors);
        $missingMentions = array_diff_key($wantedAuthors, $mentionedAuthors);
        $pathname        = $current->getFilePath();

        if (count($wasteMentions)) {
            $this->output->writeln(
                sprintf(
                    'The file <info>%s</info> is mentioning superfluous author(s):' .
                    PHP_EOL .
                    '<comment>%s</comment>',
                    $pathname,
                    implode(PHP_EOL, $wasteMentions)
                )
            );
            $validates = false;
        }

        if (count($missingMentions)) {
            $this->output->writeln(
                sprintf(
                    'The file <info>%s</info> is not mentioning its author(s):' .
                    PHP_EOL .
                    '<comment>%s</comment>',
                    $pathname,
                    implode(PHP_EOL, $missingMentions)
                )
            );
            $validates = false;
        }

        if (!$validates) {
            $this->patchExtractor($current, $wantedAuthors);
        }

        return $validates;
    }
}
