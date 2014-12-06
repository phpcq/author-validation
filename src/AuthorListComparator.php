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
     * Create a new instance.
     *
     * @param OutputInterface $output The output interface to use for logging.
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
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

        return $validates;
    }
}
