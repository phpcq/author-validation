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
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2022 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

declare(strict_types=1);

namespace PhpCodeQuality\AuthorValidation\Console;

use PhpCodeQuality\AuthorValidation\Command\CheckAuthor;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class to check the mentioned authors.
 *
 * @package PhpCodeQuality\AuthorValidation\Command
 */
class Application extends BaseApplication
{
    /**
     * Gets the name of the command based on input.
     *
     * @param InputInterface $input The input interface.
     *
     * @return string The command name
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getCommandName(InputInterface $input): string
    {
        return 'phpcq:check-author';
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands(): array
    {
        // Keep the core default commands to have the HelpCommand
        // which is used when using the --help option
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new CheckAuthor();

        return $defaultCommands;
    }

    /**
     * Overridden so that the application doesn't expect the command name to be the first argument.
     *
     * @return InputDefinition The InputDefinition instance
     */
    public function getDefinition(): InputDefinition
    {
        $inputDefinition = parent::getDefinition();
        // clear out the normal first argument, which is the command name
        $inputDefinition->setArguments();

        return $inputDefinition;
    }
}
