<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.2.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Cache\Engine;

use Cake\Cache\Cache;
use Cake\Cache\Engine\FileEngine;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use DateInterval;

/**
 * FileEngineTest class
 */
class FileEngineTest extends TestCase
{
    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        Cache::enable();
        $this->_configCache();
        Cache::clear('file_test');
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        Cache::drop('file_test');
        Cache::drop('file_groups');
        Cache::drop('file_groups2');
        Cache::drop('file_groups3');
        parent::tearDown();
    }

    /**
     * Helper method for testing.
     *
     * @param array $config
     */
    protected function _configCache($config = []): void
    {
        $defaults = [
            'className' => 'File',
            'path' => TMP . 'tests',
        ];
        Cache::drop('file_test');
        Cache::setConfig('file_test', array_merge($defaults, $config));
    }

    /**
     * Test get with default value
     */
    public function testGetDefaultValue(): void
    {
        $file = Cache::pool('file_test');
        $this->assertFalse($file->get('nope', false));
        $this->assertNull($file->get('nope', null));
        $this->assertTrue($file->get('nope', true));
        $this->assertSame(0, $file->get('nope', 0));

        $file->set('yep', 0);
        $this->assertSame(0, $file->get('yep', false));
    }

    /**
     * testReadAndWriteCache method
     */
    public function testReadAndWriteCacheExpired(): void
    {
        $this->_configCache(['duration' => 1]);

        $result = Cache::read('test', 'file_test');
        $this->assertNull($result);
    }

    /**
     * Test reading and writing to the cache.
     */
    public function testReadAndWrite(): void
    {
        $result = Cache::read('test', 'file_test');
        $this->assertNull($result);

        $data = 'this is a test of the emergency broadcasting system';
        Cache::write('test', $data, 'file_test');
        $this->assertFileExists(TMP . 'tests/cake_test');

        $result = Cache::read('test', 'file_test');
        $expecting = $data;
        $this->assertSame($expecting, $result);

        Cache::delete('test', 'file_test');
    }

    /**
     * Test read/write on the same cache key. Ensures file handles are re-wound.
     */
    public function testConsecutiveReadWrite(): void
    {
        Cache::write('rw', 'first write', 'file_test');
        $result = Cache::read('rw', 'file_test');

        Cache::write('rw', 'second write', 'file_test');
        $resultB = Cache::read('rw', 'file_test');

        Cache::delete('rw', 'file_test');
        $this->assertSame('first write', $result);
        $this->assertSame('second write', $resultB);
    }

    /**
     * testExpiry method
     */
    public function testExpiry(): void
    {
        $this->_configCache(['duration' => 1]);

        $result = Cache::read('test', 'file_test');
        $this->assertNull($result);

        $data = 'this is a test of the emergency broadcasting system';
        $result = Cache::write('other_test', $data, 'file_test');
        $this->assertTrue($result);

        sleep(2);
        $result = Cache::read('other_test', 'file_test');
        $this->assertNull($result, 'Expired key no result.');
        $this->assertSame(0, Cache::pool('file_test')->get('other_test', 0), 'expired values get default.');

        $this->_configCache(['duration' => '+1 second']);

        $data = 'this is a test of the emergency broadcasting system';
        $result = Cache::write('other_test', $data, 'file_test');
        $this->assertTrue($result);

        sleep(2);
        $result = Cache::read('other_test', 'file_test');
        $this->assertNull($result);
    }

    /**
     * test set ttl parameter
     */
    public function testSetWithTtl(): void
    {
        $this->_configCache(['duration' => 99]);
        $engine = Cache::pool('file_test');
        $this->assertNull($engine->get('test'));

        $data = 'this is a test of the emergency broadcasting system';
        $this->assertTrue($engine->set('default_ttl', $data));
        $this->assertTrue($engine->set('int_ttl', $data, 1));
        $this->assertTrue($engine->set('interval_ttl', $data, new DateInterval('PT1S')));
        $this->assertTrue($engine->setMultiple(['multi' => $data], 1));

        sleep(2);
        $this->assertNull($engine->get('int_ttl'));
        $this->assertNull($engine->get('interval_ttl'));
        $this->assertSame($data, $engine->get('default_ttl'));
        $this->assertNull($engine->get('multi'));
    }

    /**
     * Test has() method
     */
    public function testHas(): void
    {
        $engine = Cache::pool('file_test');
        $this->assertFalse($engine->has('test'));

        $this->assertTrue($engine->set('test', 1));
        $this->assertTrue($engine->has('test'));
    }

    /**
     * testDeleteCache method
     */
    public function testDeleteCache(): void
    {
        $data = 'this is a test of the emergency broadcasting system';
        $result = Cache::write('delete_test', $data, 'file_test');
        $this->assertTrue($result);

        $result = Cache::delete('delete_test', 'file_test');
        $this->assertTrue($result);
        $this->assertFileDoesNotExist(TMP . 'tests/delete_test');

        $result = Cache::delete('delete_test', 'file_test');
        $this->assertFalse($result);
    }

    /**
     * testSerialize method
     */
    public function testSerialize(): void
    {
        $this->_configCache(['serialize' => true]);
        $data = 'this is a test of the emergency broadcasting system';
        $write = Cache::write('serialize_test', $data, 'file_test');
        $this->assertTrue($write);

        $this->_configCache(['serialize' => false]);
        $read = Cache::read('serialize_test', 'file_test');

        Cache::delete('serialize_test', 'file_test');
        $this->assertSame($read, serialize($data));
        $this->assertSame(unserialize($read), $data);
    }

    /**
     * testClear method
     */
    public function testClear(): void
    {
        $this->_configCache(['duration' => 0]);

        $data = 'this is a test of the emergency broadcasting system';
        Cache::write('serialize_test1', $data, 'file_test');
        Cache::write('serialize_test2', $data, 'file_test');
        Cache::write('serialize_test3', $data, 'file_test');
        $this->assertFileExists(TMP . 'tests/cake_serialize_test1');
        $this->assertFileExists(TMP . 'tests/cake_serialize_test2');
        $this->assertFileExists(TMP . 'tests/cake_serialize_test3');

        $result = Cache::clear('file_test');
        $this->assertTrue($result);
        $this->assertFileDoesNotExist(TMP . 'tests/cake_serialize_test1');
        $this->assertFileDoesNotExist(TMP . 'tests/cake_serialize_test2');
        $this->assertFileDoesNotExist(TMP . 'tests/cake_serialize_test3');
    }

    /**
     * test that clear() doesn't wipe files not in the current engine's prefix.
     */
    public function testClearWithPrefixes(): void
    {
        $FileOne = new FileEngine();
        $FileOne->init([
            'prefix' => 'prefix_one_',
            'duration' => 3600,
        ]);
        $FileTwo = new FileEngine();
        $FileTwo->init([
            'prefix' => 'prefix_two_',
            'duration' => 3600,
        ]);
        $dataOne = 'content to cache';
        $dataTwo = 'content to cache';
        $expected = 'content to cache';
        $FileOne->set('prefix_one_key_one', $dataOne);
        $FileTwo->set('prefix_two_key_two', $dataTwo);

        $this->assertSame($expected, $FileOne->get('prefix_one_key_one'));
        $this->assertSame($expected, $FileTwo->get('prefix_two_key_two'));

        $FileOne->clear();
        $this->assertSame($expected, $FileTwo->get('prefix_two_key_two'), 'secondary config was cleared by accident.');
        $FileTwo->clear();
    }

    /**
     * Test that clear() also removes files with group tags.
     */
    public function testClearWithGroups(): void
    {
        $engine = new FileEngine();
        $engine->init([
            'prefix' => 'cake_test_',
            'duration' => 3600,
            'groups' => ['short', 'round'],
        ]);
        $key = 'cake_test_test_key';
        $engine->set($key, 'it works');
        $engine->clear();
        $this->assertNull($engine->get($key), 'Key should have been removed');
    }

    /**
     * Test that clear() also removes files with group tags.
     */
    public function testClearWithNoKeys(): void
    {
        $engine = new FileEngine();
        $engine->init([
            'prefix' => 'cake_test_',
            'duration' => 3600,
            'groups' => ['one', 'two'],
        ]);
        $key = 'cake_test_test_key';
        $engine->clear();
        $this->assertNull($engine->get($key), 'No errors should be found');
    }

    /**
     * testKeyPath method
     */
    public function testKeyPath(): void
    {
        $result = Cache::write('views.countries.something', 'here', 'file_test');
        $this->assertTrue($result);
        $this->assertFileExists(TMP . 'tests/cake_views.countries.something');

        $result = Cache::read('views.countries.something', 'file_test');
        $this->assertSame('here', $result);

        $key = 'colon:quote"slash/brackets[]';
        $result = Cache::write($key, 'here', 'file_test');
        $this->assertTrue($result);
        $this->assertFileExists(TMP . 'tests/cake_colon%3Aquote%22slash%2Fbrackets%5B%5D');

        $result = Cache::read($key, 'file_test');
        $this->assertSame('here', $result);

        $result = Cache::clear('file_test');
        $this->assertTrue($result);
    }

    /**
     * testRemoveWindowsSlashesFromCache method
     */
    public function testRemoveWindowsSlashesFromCache(): void
    {
        Cache::setConfig('windows_test', [
            'engine' => 'File',
            'prefix' => null,
            'path' => CACHE,
        ]);

        $expected = [
            'C:\dev\prj2\sites\cake\libs' => [
                0 => 'C:\dev\prj2\sites\cake\libs', 1 => 'C:\dev\prj2\sites\cake\libs\view',
                2 => 'C:\dev\prj2\sites\cake\libs\view\scaffolds', 3 => 'C:\dev\prj2\sites\cake\libs\view\pages',
                4 => 'C:\dev\prj2\sites\cake\libs\view\layouts', 5 => 'C:\dev\prj2\sites\cake\libs\view\layouts\xml',
                6 => 'C:\dev\prj2\sites\cake\libs\view\layouts\rss', 7 => 'C:\dev\prj2\sites\cake\libs\view\layouts\js',
                8 => 'C:\dev\prj2\sites\cake\libs\view\layouts\email', 9 => 'C:\dev\prj2\sites\cake\libs\view\layouts\email\text',
                10 => 'C:\dev\prj2\sites\cake\libs\view\layouts\email\html', 11 => 'C:\dev\prj2\sites\cake\libs\view\helpers',
                12 => 'C:\dev\prj2\sites\cake\libs\view\errors', 13 => 'C:\dev\prj2\sites\cake\libs\view\elements',
                14 => 'C:\dev\prj2\sites\cake\libs\view\elements\email', 15 => 'C:\dev\prj2\sites\cake\libs\view\elements\email\text',
                16 => 'C:\dev\prj2\sites\cake\libs\view\elements\email\html', 17 => 'C:\dev\prj2\sites\cake\libs\model',
                18 => 'C:\dev\prj2\sites\cake\libs\model\datasources', 19 => 'C:\dev\prj2\sites\cake\libs\model\datasources\dbo',
                20 => 'C:\dev\prj2\sites\cake\libs\model\behaviors', 21 => 'C:\dev\prj2\sites\cake\libs\controller',
                22 => 'C:\dev\prj2\sites\cake\libs\controller\components', 23 => 'C:\dev\prj2\sites\cake\libs\cache'],
            'C:\dev\prj2\sites\main_site\vendors' => [
                0 => 'C:\dev\prj2\sites\main_site\vendors', 1 => 'C:\dev\prj2\sites\main_site\vendors\shells',
                2 => 'C:\dev\prj2\sites\main_site\vendors\shells\templates', 3 => 'C:\dev\prj2\sites\main_site\vendors\shells\templates\cdc_project',
                4 => 'C:\dev\prj2\sites\main_site\vendors\shells\tasks', 5 => 'C:\dev\prj2\sites\main_site\vendors\js',
                6 => 'C:\dev\prj2\sites\main_site\vendors\css'],
            'C:\dev\prj2\sites\vendors' => [
                0 => 'C:\dev\prj2\sites\vendors', 1 => 'C:\dev\prj2\sites\vendors\simpletest',
                2 => 'C:\dev\prj2\sites\vendors\simpletest\test', 3 => 'C:\dev\prj2\sites\vendors\simpletest\test\support',
                4 => 'C:\dev\prj2\sites\vendors\simpletest\test\support\collector', 5 => 'C:\dev\prj2\sites\vendors\simpletest\extensions',
                6 => 'C:\dev\prj2\sites\vendors\simpletest\extensions\testdox', 7 => 'C:\dev\prj2\sites\vendors\simpletest\docs',
                8 => 'C:\dev\prj2\sites\vendors\simpletest\docs\fr', 9 => 'C:\dev\prj2\sites\vendors\simpletest\docs\en'],
            'C:\dev\prj2\sites\main_site\views\helpers' => [
                0 => 'C:\dev\prj2\sites\main_site\views\helpers'],
        ];

        Cache::write('test_dir_map', $expected, 'windows_test');
        $data = Cache::read('test_dir_map', 'windows_test');
        Cache::delete('test_dir_map', 'windows_test');
        $this->assertEquals($expected, $data);

        Cache::drop('windows_test');
    }

    /**
     * testWriteQuotedString method
     */
    public function testWriteQuotedString(): void
    {
        Cache::write('App.doubleQuoteTest', '"this is a quoted string"', 'file_test');
        $this->assertSame(Cache::read('App.doubleQuoteTest', 'file_test'), '"this is a quoted string"');
        Cache::write('App.singleQuoteTest', "'this is a quoted string'", 'file_test');
        $this->assertSame(Cache::read('App.singleQuoteTest', 'file_test'), "'this is a quoted string'");

        Cache::drop('file_test');
        Cache::setConfig('file_test', [
            'className' => 'File',
            'isWindows' => true,
            'path' => TMP . 'tests',
        ]);

        $this->assertSame(Cache::read('App.doubleQuoteTest', 'file_test'), '"this is a quoted string"');
        Cache::write('App.singleQuoteTest', "'this is a quoted string'", 'file_test');
        $this->assertSame(Cache::read('App.singleQuoteTest', 'file_test'), "'this is a quoted string'");
        Cache::delete('App.singleQuoteTest', 'file_test');
        Cache::delete('App.doubleQuoteTest', 'file_test');
    }

    /**
     * check that FileEngine does not generate an error when a configured Path does not exist in debug mode.
     */
    public function testPathDoesNotExist(): void
    {
        Configure::write('debug', true);
        $dir = TMP . 'tests/autocreate-' . microtime(true);

        Cache::drop('file_test');
        Cache::setConfig('file_test', [
            'engine' => 'File',
            'path' => $dir,
        ]);

        Cache::read('Test', 'file_test');
        $this->assertFileExists($dir, 'Dir should exist.');

        // Cleanup
        rmdir($dir);
    }

    /**
     * Test that under debug 0 directories do get made.
     */
    public function testPathDoesNotExistDebugOff(): void
    {
        Configure::write('debug', false);
        $dir = TMP . 'tests/autocreate-' . microtime(true);

        Cache::drop('file_test');
        Cache::setConfig('file_test', [
            'engine' => 'File',
            'path' => $dir,
        ]);

        Cache::read('Test', 'file_test');
        $this->assertFileExists($dir, 'Dir should exist.');

        // Cleanup
        rmdir($dir);
    }

    /**
     * Testing the mask setting in FileEngine
     */
    public function testMaskSetting(): void
    {
        if (DS === '\\') {
            $this->markTestSkipped('File permission testing does not work on Windows.');
        }
        Cache::setConfig('mask_test', ['engine' => 'File', 'path' => TMP . 'tests']);
        $data = 'This is some test content';
        Cache::write('masking_test', $data, 'mask_test');
        $result = substr(sprintf('%o', fileperms(TMP . 'tests/cake_masking_test')), -4);
        $expected = '0664';
        $this->assertSame($expected, $result);
        Cache::delete('masking_test', 'mask_test');
        Cache::drop('mask_test');

        Cache::setConfig('mask_test', ['engine' => 'File', 'mask' => 0666, 'path' => TMP . 'tests']);
        Cache::write('masking_test', $data, 'mask_test');
        $result = substr(sprintf('%o', fileperms(TMP . 'tests/cake_masking_test')), -4);
        $expected = '0666';
        $this->assertSame($expected, $result);
        Cache::delete('masking_test', 'mask_test');
        Cache::drop('mask_test');

        Cache::setConfig('mask_test', ['engine' => 'File', 'mask' => 0644, 'path' => TMP . 'tests']);
        Cache::write('masking_test', $data, 'mask_test');
        $result = substr(sprintf('%o', fileperms(TMP . 'tests/cake_masking_test')), -4);
        $expected = '0644';
        $this->assertSame($expected, $result);
        Cache::delete('masking_test', 'mask_test');
        Cache::drop('mask_test');

        Cache::setConfig('mask_test', ['engine' => 'File', 'mask' => 0640, 'path' => TMP . 'tests']);
        Cache::write('masking_test', $data, 'mask_test');
        $result = substr(sprintf('%o', fileperms(TMP . 'tests/cake_masking_test')), -4);
        $expected = '0640';
        $this->assertSame($expected, $result);
        Cache::delete('masking_test', 'mask_test');
        Cache::drop('mask_test');
    }

    /**
     * Tests that configuring groups for stored keys return the correct values when read/written
     */
    public function testGroupsReadWrite(): void
    {
        Cache::setConfig('file_groups', [
            'engine' => 'File',
            'duration' => 3600,
            'groups' => ['group_a', 'group_b'],
        ]);
        $this->assertTrue(Cache::write('test_groups', 'value', 'file_groups'));
        $this->assertSame('value', Cache::read('test_groups', 'file_groups'));

        $this->assertTrue(Cache::write('test_groups2', 'value2', 'file_groups'));
        $this->assertTrue(Cache::write('test_groups3', 'value3', 'file_groups'));
    }

    /**
     * Test that clearing with repeat writes works properly
     */
    public function testClearingWithRepeatWrites(): void
    {
        Cache::setConfig('repeat', [
            'engine' => 'File',
            'groups' => ['users'],
        ]);

        $this->assertTrue(Cache::write('user', 'rchavik', 'repeat'));
        $this->assertSame('rchavik', Cache::read('user', 'repeat'));

        Cache::delete('user', 'repeat');
        $this->assertNull(Cache::read('user', 'repeat'));

        $this->assertTrue(Cache::write('user', 'ADmad', 'repeat'));
        $this->assertSame('ADmad', Cache::read('user', 'repeat'));

        Cache::clearGroup('users', 'repeat');
        $this->assertNull(Cache::read('user', 'repeat'));

        $this->assertTrue(Cache::write('user', 'markstory', 'repeat'));
        $this->assertSame('markstory', Cache::read('user', 'repeat'));

        Cache::drop('repeat');
    }

    /**
     * Tests that deleting from a groups-enabled config is possible
     */
    public function testGroupDelete(): void
    {
        Cache::setConfig('file_groups', [
            'engine' => 'File',
            'duration' => 3600,
            'groups' => ['group_a', 'group_b'],
        ]);
        $this->assertTrue(Cache::write('test_groups', 'value', 'file_groups'));
        $this->assertSame('value', Cache::read('test_groups', 'file_groups'));
        $this->assertTrue(Cache::delete('test_groups', 'file_groups'));

        $this->assertNull(Cache::read('test_groups', 'file_groups'));
    }

    /**
     * Test clearing a cache group
     */
    public function testGroupClear(): void
    {
        Cache::setConfig('file_groups', [
            'engine' => 'File',
            'duration' => 3600,
            'groups' => ['group_a', 'group_b'],
        ]);
        Cache::setConfig('file_groups2', [
            'engine' => 'File',
            'duration' => 3600,
            'groups' => ['group_b'],
        ]);
        Cache::setConfig('file_groups3', [
            'engine' => 'File',
            'duration' => 3600,
            'groups' => ['group_b'],
            'prefix' => 'leading_',
        ]);

        $this->assertTrue(Cache::write('test_groups', 'value', 'file_groups'));
        $this->assertTrue(Cache::write('test_groups2', 'value 2', 'file_groups2'));
        $this->assertTrue(Cache::write('test_groups3', 'value 3', 'file_groups3'));

        $this->assertTrue(Cache::clearGroup('group_b', 'file_groups'));
        $this->assertNull(Cache::read('test_groups', 'file_groups'));
        $this->assertNull(Cache::read('test_groups2', 'file_groups2'));
        $this->assertSame('value 3', Cache::read('test_groups3', 'file_groups3'));

        $this->assertTrue(Cache::write('test_groups4', 'value', 'file_groups'));
        $this->assertTrue(Cache::write('test_groups5', 'value 2', 'file_groups2'));
        $this->assertTrue(Cache::write('test_groups6', 'value 3', 'file_groups3'));

        $this->assertTrue(Cache::clearGroup('group_b', 'file_groups'));
        $this->assertNull(Cache::read('test_groups4', 'file_groups'));
        $this->assertNull(Cache::read('test_groups5', 'file_groups2'));
        $this->assertSame('value 3', Cache::read('test_groups6', 'file_groups3'));
    }

    /**
     * Test that clearGroup works with no prefix.
     */
    public function testGroupClearNoPrefix(): void
    {
        Cache::setConfig('file_groups', [
            'className' => 'File',
            'duration' => 3600,
            'prefix' => '',
            'groups' => ['group_a', 'group_b'],
        ]);
        Cache::write('key_1', 'value', 'file_groups');
        Cache::write('key_2', 'value', 'file_groups');
        Cache::clearGroup('group_a', 'file_groups');
        $this->assertNull(Cache::read('key_1', 'file_groups'), 'Did not delete');
        $this->assertNull(Cache::read('key_2', 'file_groups'), 'Did not delete');
    }

    /**
     * Test that failed add write return false.
     */
    public function testAdd(): void
    {
        Cache::delete('test_add_key', 'file_test');

        $result = Cache::add('test_add_key', 'test data', 'file_test');
        $this->assertTrue($result);

        $expected = 'test data';
        $result = Cache::read('test_add_key', 'file_test');
        $this->assertSame($expected, $result);

        $result = Cache::add('test_add_key', 'test data 2', 'file_test');
        $this->assertFalse($result);
    }

    /**
     * Tests that only files inside of the configured path are being deleted.
     */
    public function testClearIsRestrictedToConfiguredPath(): void
    {
        $this->_configCache([
            'prefix' => '',
            'path' => TMP . 'tests',
        ]);

        $unrelatedFile = tempnam(TMP, 'file_test');
        file_put_contents($unrelatedFile, 'data');
        $this->assertFileExists($unrelatedFile);

        Cache::write('key', 'data', 'file_test');
        $this->assertFileExists(TMP . 'tests/key');

        $result = Cache::clear('file_test');
        $this->assertTrue($result);
        $this->assertFileDoesNotExist(TMP . 'tests/key');

        $this->assertFileExists($unrelatedFile);
        $this->assertTrue(unlink($unrelatedFile));
    }
}
