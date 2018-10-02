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
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2014-2018 Christian Schiffler <c.schiffler@cyberspectrum.de>, Tristan Lins <tristan@lins.io>
 * @license    https://github.com/phpcq/author-validation/blob/master/LICENSE MIT
 * @link       https://github.com/phpcq/author-validation
 * @filesource
 */

namespace PhpCodeQuality\AuthorValidation\Test\AuthorExtractor;


use PhpCodeQuality\AuthorValidation\AuthorExtractor\PhpDocAuthorExtractor;
use PhpCodeQuality\AuthorValidation\Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Test class PhpDocAuthorExtractor.
 */
class PhpDocAuthorExtractorTest extends TestCase
{
    /**
     * Test the setAuthors() method.
     */
    public function testSetAuthors()
    {
        $cache     = $this->getMockBuilder('\Doctrine\Common\Cache\Cache')->getMock();
        $output    = new BufferedOutput();
        $extractor = new PhpDocAuthorExtractor(new Config(), $output, $cache);
        $this->markTestIncomplete('Unimplemented so far.');
    }
}
