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

namespace ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Test;

use ContaoCommunityAlliance\BuildSystem\Tool\AuthorValidation\Config;

/**
 * Test the config class.
 */
class ConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test the author mapping.
     *
     * @return void
     */
    public function testAuthorMapping()
    {
        $config = new Config();

        $config->addAuthorMap(array(
            'Single Source <single-real@example.org>' => 'Single Alias <single-alias@example.org>',
            'Multiple Source <multiple-real@example.org>' => array(
                'Multiple Alias1 <multiple-alias1@example.org>',
                'Multiple Alias2 <multiple-alias2@example.org>'
            )
        ));

        $this->assertFalse($config->isAlias('Single Source <single-real@example.org>'));
        $this->assertFalse($config->isAlias('Multiple Source <multiple-real@example.org>'));
        $this->assertTrue($config->isAlias('Single Alias <single-alias@example.org>'));
        $this->assertTrue($config->isAlias('Multiple Alias1 <multiple-alias1@example.org>'));
        $this->assertTrue($config->isAlias('Multiple Alias2 <multiple-alias2@example.org>'));

        $this->assertEquals(
            'Single Source <single-real@example.org>',
            $config->getRealAuthor('Single Alias <single-alias@example.org>')
        );
        $this->assertEquals(
            'Multiple Source <multiple-real@example.org>',
            $config->getRealAuthor('Multiple Alias1 <multiple-alias1@example.org>')
        );
        $this->assertEquals(
            'Multiple Source <multiple-real@example.org>',
            $config->getRealAuthor('Multiple Alias2 <multiple-alias2@example.org>')
        );

        $this->assertEquals(
            'Single Source <single-real@example.org>',
            $config->getRealAuthor('Single Source <single-real@example.org>')
        );

        $this->assertEquals(
            'Multiple Source <multiple-real@example.org>',
            $config->getRealAuthor('Multiple Source <multiple-real@example.org>')
        );
    }

    /**
     * Test the author mapping.
     *
     * @return void
     */
    public function testIgnoredAuthors()
    {
        $config = new Config();

        $config->aliasAuthor('Author Alias <single-alias@example.org>', 'Real Author <real@example.org>');
        $config->aliasAuthor('Ignored Alias <ignored-alias@example.org>', 'Real Author <real@example.org>');
        $config->ignoreAuthors(
            array('Ignored Author <ignored@example.org>', 'Ignored Alias <ignored-alias@example.org>')
        );

        $this->assertFalse($config->isAuthorIgnored('Author Alias <single-alias@example.org>'));
        $this->assertFalse($config->isAuthorIgnored('Real Author <real@example.org>'));
        $this->assertTrue($config->isAuthorIgnored('Ignored Author <ignored@example.org>'));
        $this->assertTrue($config->isAuthorIgnored('Ignored Alias <ignored-alias@example.org>'));
    }

    /**
     * Test the author mapping against ignored authors.
     *
     * @return void
     */
    public function testIgnoredAuthorMapping()
    {
        $config = new Config();

        $config->aliasAuthor('Author Alias <single-alias@example.org>', 'Real Author <real@example.org>');
        $config->aliasAuthor('Ignored Alias <ignored-alias@example.org>', 'Real Author <real@example.org>');
        $config->aliasAuthor('Ignored Alias2 <ignored-alias@example.org>', 'Ignored Author <ignored@example.org>');
        $config->ignoreAuthors(
            array(
                'Ignored Author <ignored@example.org>',
                'Ignored Alias <ignored-alias@example.org>'
            )
        );

        $this->assertFalse($config->isAlias('Real Author <real@example.org>'));
        $this->assertFalse($config->isAlias('Ignored Author <ignored@example.org>'));

        $this->assertNull($config->getRealAuthor('Ignored Author <ignored@example.org>'));
        $this->assertNull($config->getRealAuthor('Ignored Alias <ignored-alias@example.org>'));
        $this->assertNull($config->getRealAuthor('Ignored Alias2 <ignored-alias@example.org>'));
        $this->assertEquals(
            'Real Author <real@example.org>',
            $config->getRealAuthor('Real Author <real@example.org>')
        );
        $this->assertEquals(
            'Real Author <real@example.org>',
            $config->getRealAuthor('Author Alias <single-alias@example.org>')
        );
    }

    /**
     * Test the pattern matching.
     *
     * @return void
     */
    public function testMatchPattern()
    {
        $this->markTestIncomplete(
            'Double asterisk globing is currently handled the same way as single asterisk globing'
        );

        $config = new Config();

        $reflection = new \ReflectionMethod($config, 'matchPattern');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($config, '/a/b/z', '/a/*/z'));
        $this->assertTrue($reflection->invoke($config, '/a/c/z', '/a/*/z'));
        //$this->assertFalse($reflection->invoke($config, '/a/b/c/z', '/a/*/z'));
        $this->assertTrue($reflection->invoke($config, '/a/b/z', '/a/**/z'));
        $this->assertTrue($reflection->invoke($config, '/a/b/c/z', '/a/**/z'));
        $this->assertTrue($reflection->invoke($config, '/a/b/c/d/e/f/g/h/i/z', '/a/**/z'));
        //$this->assertFalse($reflection->invoke($config, '/a/b/c/z/d', '/a/**/z'));

        $this->assertTrue($reflection->invoke($config, '/some/dir/File.php', 'File.php'));
    }

    /**
     * Test the author mapping.
     *
     * @return void
     */
    public function testCopyLeftAuthors()
    {
        $config = new Config();

        $config->addCopyLeft('Copy Left <user@example.org>', 'File.php');
        $config->addCopyLeft('Copy Left <user@example.org>', array('File2.php', '/lib/**'));

        $this->assertTrue($config->isCopyLeftAuthor('Copy Left <user@example.org>', '/some/dir/File.php'));
        $this->assertTrue($config->isCopyLeftAuthor('Copy Left <user@example.org>', '/some/dir/File2.php'));
        $this->assertTrue($config->isCopyLeftAuthor('Copy Left <user@example.org>', '/lib/dir/File.php'));
        $this->assertFalse($config->isCopyLeftAuthor('Copy Left <user@example.org>', '/some/dir/File3.php'));
        $this->assertFalse($config->isCopyLeftAuthor('Unknown <user@example.org>', '/some/dir/File3.php'));
    }
}
