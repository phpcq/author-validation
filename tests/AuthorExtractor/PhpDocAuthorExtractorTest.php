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

namespace PhpCodeQuality\AuthorValidation\Test\AuthorExtractor;


use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\AuthorExtractor\PhpDocAuthorExtractor;
use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Config;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Test class PhpDocAuthorExtractor.
 */
class PhpDocAuthorExtractorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test the setAuthors() method.
     */
    public function testSetAuthors()
    {
        $output    = new BufferedOutput();
        $extractor = new PhpDocAuthorExtractor(new Config(), $output);
        $this->markTestIncomplete('Unimplemented so far.');
    }
}
