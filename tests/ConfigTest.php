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

namespace PhpCodeQuality\AuthorValidation\Test;

use PhpCodeQuality\AuthorValidation\Config;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Test the config class.
 *
 * @covers \PhpCodeQuality\AuthorValidation\Config
 */
class ConfigTest extends TestCase
{
    /**
     * Test the author mapping.
     *
     * @return void
     */
    public function testAuthorMapping(): void
    {
        $config = new Config();

        $config->addAuthorMap([
            'Single Source <single-real@example.org>' => 'Single Alias <single-alias@example.org>',
            'Multiple Source <multiple-real@example.org>' => [
                'Multiple Alias1 <multiple-alias1@example.org>',
                'Multiple Alias2 <multiple-alias2@example.org>'
            ]
                              ]);

        static::assertFalse($config->isAlias('Single Source <single-real@example.org>'));
        static::assertFalse($config->isAlias('Multiple Source <multiple-real@example.org>'));
        static::assertTrue($config->isAlias('Single Alias <single-alias@example.org>'));
        static::assertTrue($config->isAlias('Multiple Alias1 <multiple-alias1@example.org>'));
        static::assertTrue($config->isAlias('Multiple Alias2 <multiple-alias2@example.org>'));

        static::assertEquals(
            'Single Source <single-real@example.org>',
            $config->getRealAuthor('Single Alias <single-alias@example.org>')
        );
        static::assertEquals(
            'Multiple Source <multiple-real@example.org>',
            $config->getRealAuthor('Multiple Alias1 <multiple-alias1@example.org>')
        );
        static::assertEquals(
            'Multiple Source <multiple-real@example.org>',
            $config->getRealAuthor('Multiple Alias2 <multiple-alias2@example.org>')
        );

        static::assertEquals(
            'Single Source <single-real@example.org>',
            $config->getRealAuthor('Single Source <single-real@example.org>')
        );

        static::assertEquals(
            'Multiple Source <multiple-real@example.org>',
            $config->getRealAuthor('Multiple Source <multiple-real@example.org>')
        );
    }

    /**
     * Test the author mapping.
     *
     * @return void
     */
    public function testIgnoredAuthors(): void
    {
        $config = new Config();

        $config->aliasAuthor('Author Alias <single-alias@example.org>', 'Real Author <real@example.org>');
        $config->aliasAuthor('Ignored Alias <ignored-alias@example.org>', 'Real Author <real@example.org>');
        $config->ignoreAuthors(
            ['Ignored Author <ignored@example.org>', 'Ignored Alias <ignored-alias@example.org>']
        );

        static::assertFalse($config->isAuthorIgnored('Author Alias <single-alias@example.org>'));
        static::assertFalse($config->isAuthorIgnored('Real Author <real@example.org>'));
        static::assertTrue($config->isAuthorIgnored('Ignored Author <ignored@example.org>'));
        static::assertTrue($config->isAuthorIgnored('Ignored Alias <ignored-alias@example.org>'));
    }

    /**
     * Test the author mapping against ignored authors.
     *
     * @return void
     */
    public function testIgnoredAuthorMapping(): void
    {
        $config = new Config();

        $config->aliasAuthor('Author Alias <single-alias@example.org>', 'Real Author <real@example.org>');
        $config->aliasAuthor('Ignored Alias <ignored-alias@example.org>', 'Real Author <real@example.org>');
        $config->aliasAuthor('Ignored Alias2 <ignored-alias@example.org>', 'Ignored Author <ignored@example.org>');
        $config->ignoreAuthors(
            [
                'Ignored Author <ignored@example.org>',
                'Ignored Alias <ignored-alias@example.org>'
            ]
        );

        static::assertFalse($config->isAlias('Real Author <real@example.org>'));
        static::assertFalse($config->isAlias('Ignored Author <ignored@example.org>'));

        static::assertNull($config->getRealAuthor('Ignored Author <ignored@example.org>'));
        static::assertNull($config->getRealAuthor('Ignored Alias <ignored-alias@example.org>'));
        static::assertNull($config->getRealAuthor('Ignored Alias2 <ignored-alias@example.org>'));
        static::assertEquals(
            'Real Author <real@example.org>',
            $config->getRealAuthor('Real Author <real@example.org>')
        );
        static::assertEquals(
            'Real Author <real@example.org>',
            $config->getRealAuthor('Author Alias <single-alias@example.org>')
        );
    }

    /**
     * Test the pattern matching.
     *
     * @return void
     */
    public function testMatchPattern(): void
    {
        static::markTestIncomplete(
            'Double asterisk globing is currently handled the same way as single asterisk globing'
        );

        $config = new Config();

        $reflection = new ReflectionMethod($config, 'matchPattern');
        $reflection->setAccessible(true);

        static::assertTrue($reflection->invoke($config, '/a/b/z', '/a/*/z'));
        static::assertTrue($reflection->invoke($config, '/a/c/z', '/a/*/z'));
        //$this->assertFalse($reflection->invoke($config, '/a/b/c/z', '/a/*/z'));
        static::assertTrue($reflection->invoke($config, '/a/b/z', '/a/**/z'));
        static::assertTrue($reflection->invoke($config, '/a/b/c/z', '/a/**/z'));
        static::assertTrue($reflection->invoke($config, '/a/b/c/d/e/f/g/h/i/z', '/a/**/z'));
        //$this->assertFalse($reflection->invoke($config, '/a/b/c/z/d', '/a/**/z'));

        static::assertTrue($reflection->invoke($config, '/some/dir/File.php', 'File.php'));
    }

    /**
     * Test the author mapping.
     *
     * @return void
     */
    public function testCopyLeftAuthors(): void
    {
        $config = new Config();

        $config->addCopyLeft('Copy Left <user@example.org>', 'File.php');
        foreach (['File2.php', '/lib/**'] as $singlePattern) {
            $config->addCopyLeft('Copy Left <user@example.org>', $singlePattern);
        }
        $config->addCopyLeft('Copy Left2 <user@example.org>', 'some/dir/File4.php');

        static::assertTrue($config->isCopyLeftAuthor('Copy Left <user@example.org>', '/some/dir/File.php'));
        static::assertTrue($config->isCopyLeftAuthor('Copy Left <user@example.org>', '/some/dir/File2.php'));
        static::assertTrue($config->isCopyLeftAuthor('Copy Left <user@example.org>', '/lib/dir/File.php'));
        static::assertFalse($config->isCopyLeftAuthor('Copy Left <user@example.org>', '/some/dir/File3.php'));
        static::assertFalse($config->isCopyLeftAuthor('Unknown <user@example.org>', '/some/dir/File3.php'));
        static::assertTrue($config->isCopyLeftAuthor('Copy Left2 <user@example.org>', '/lib/some/dir/File4.php'));
        static::assertTrue($config->isCopyLeftAuthor('Copy Left2 <user@example.org>', '/some/dir/File4.php'));
    }
}
