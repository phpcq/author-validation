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
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2022 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @link       https://github.com/phpcq/author-validation
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation\Test\Console;

use PhpCodeQuality\AuthorValidation\Console\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @covers \PhpCodeQuality\AuthorValidation\Console\Application
 * @covers \PhpCodeQuality\AuthorValidation\Command\CheckAuthor
 */
class ApplicationTest extends TestCase
{
    public function testApplication(): void
    {
        $input = new ArrayInput(['--help' => '']);
        $output = new TestOutput();

        $application = new Application();
        self::assertSame($application->doRun($input, $output), 0);
        self::assertNotEmpty($output->output);
        self::assertTrue($application->has('phpcq:check-author'));
        $application->setAutoExit(false);
        self::assertFalse($application->isAutoExitEnabled());
        $application->setAutoExit(true);
        self::assertTrue($application->isAutoExitEnabled());
    }
}
