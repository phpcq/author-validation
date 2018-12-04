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
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Test class PhpDocAuthorExtractor.
 */
class PhpDocAuthorExtractorTest extends TestCase
{
    /**
     * Test.
     */
    public function testRemovesDuplicateAuthors()
    {
        $cache     = $this->getMockBuilder(CacheInterface::class)->getMockForAbstractClass();
        $output    = new BufferedOutput();
        $extractor = new PhpDocAuthorExtractor(new Config(), $output, $cache);

        $result = $extractor->getBuffer(
            __DIR__ . '/Fixtures/phpdoc-duplicate-authors.php',
            [
                'Author1 <author1@example.com>' => 'Author1 <author1@example.com>',
                'Author2 <author2@example.com>' => 'Author2 <author2@example.com>',
                'Author3 <author3@example.com>' => 'Author3 <author3@example.com>',
            ]
        );

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/phpdoc-authors1-3.php',
            $result
        );
    }

    /**
     * Test.
     */
    public function testOverwritesAuthors()
    {
        $cache     = $this->getMockBuilder(CacheInterface::class)->getMockForAbstractClass();
        $output    = new BufferedOutput();
        $extractor = new PhpDocAuthorExtractor(new Config(), $output, $cache);

        $result = $extractor->getBuffer(
            __DIR__ . '/Fixtures/phpdoc-authors1-3.php',
            [
                'Author4 <author4@example.com>' => 'Author4 <author4@example.com>',
                'Author5 <author5@example.com>' => 'Author5 <author5@example.com>',
            ]
        );

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/phpdoc-authors4-5.php',
            $result
        );
    }

    /**
     * Test.
     */
    public function testAddsAuthors()
    {
        $cache     = $this->getMockBuilder(CacheInterface::class)->getMockForAbstractClass();
        $output    = new BufferedOutput();
        $extractor = new PhpDocAuthorExtractor(new Config(), $output, $cache);

        $result = $extractor->getBuffer(
            __DIR__ . '/Fixtures/phpdoc-authors1-3.php',
            [
                'Author1 <author1@example.com>' => 'Author1 <author1@example.com>',
                'Author2 <author2@example.com>' => 'Author2 <author2@example.com>',
                'Author3 <author3@example.com>' => 'Author3 <author3@example.com>',
                'Author4 <author4@example.com>' => 'Author4 <author4@example.com>',
                'Author5 <author5@example.com>' => 'Author5 <author5@example.com>',
                'Author6 <author6@example.com>' => 'Author6 <author6@example.com>',
            ]
        );

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/phpdoc-authors1-6.php',
            $result
        );
    }
}
