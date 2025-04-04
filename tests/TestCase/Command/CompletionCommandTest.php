<?php
declare(strict_types=1);

/**
 * CakePHP :  Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP Project
 * @since         2.5.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Command;

use Cake\Console\CommandInterface;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;

/**
 * CompletionCommandTest
 */
class CompletionCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        static::setAppNamespace();
        Configure::write('Plugins.autoload', ['TestPlugin', 'TestPluginTwo']);
    }

    /**
     * tearDown
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Router::reload();
        $this->clearPlugins();
    }

    /**
     * test that the startup method suppresses the command header
     */
    public function testStartup(): void
    {
        $this->exec('completion');
        $this->assertExitCode(CommandInterface::CODE_ERROR);

        $this->assertOutputNotContains('Welcome to CakePHP');
    }

    /**
     * test commands method that list all available commands
     */
    public function testCommands(): void
    {
        $this->exec('completion commands');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = [
            'test_plugin.example',
            'test_plugin.sample',
            'test_plugin_two.example',
            'unique',
            'welcome',
            'cache',
            'help',
            'i18n',
            'plugin',
            'routes',
            'schema_cache',
            'server',
            'version',
            'abort',
            'auto_load_model',
            'demo',
            'integration',
            'sample',
        ];
        foreach ($expected as $value) {
            $this->assertOutputContains($value);
        }
    }

    /**
     * test that options without argument returns nothing
     */
    public function testOptionsNoArguments(): void
    {
        $this->exec('completion options');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);
        $this->assertOutputEmpty();
    }

    /**
     * test that options with a nonexistent command returns nothing
     */
    public function testOptionsNonExistentCommand(): void
    {
        $this->exec('completion options foo');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);
        $this->assertOutputEmpty();
    }

    /**
     * test that options with an existing command returns the proper options
     */
    public function testOptionsCommand(): void
    {
        $this->exec('completion options schema_cache');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = [
            '--connection -c',
            '--help -h',
            '--quiet -q',
            '--verbose -v',
        ];
        foreach ($expected as $value) {
            $this->assertOutputContains($value);
        }
    }

    /**
     * test that options with an existing command / subcommand pair returns the proper options
     */
    public function testOptionsSubCommand(): void
    {
        $this->exec('completion options cache list');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = [
            '--help -h',
            '--quiet -q',
            '--verbose -v',
        ];
        foreach ($expected as $value) {
            $this->assertOutputContains($value);
        }
    }

    /**
     * test that nested command returns subcommand's options not command.
     */
    public function testOptionsNestedCommand(): void
    {
        $this->exec('completion options i18n extract');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = [
            '--plugin',
            '--app',
        ];
        foreach ($expected as $value) {
            $this->assertOutputContains($value);
        }
    }

    /**
     * test that subCommands with a existing CORE command returns the proper sub commands
     */
    public function testSubCommandsCorePlugin(): void
    {
        $this->exec('completion subcommands schema_cache');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = 'build clear';
        $this->assertOutputContains($expected);
    }

    /**
     * test that subCommands with a existing APP command returns the proper sub commands (in this case none)
     */
    public function testSubCommandsAppPlugin(): void
    {
        $this->exec('completion subcommands sample');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);
        $this->assertOutputContains('sub');
    }

    /**
     * test that subCommands with a existing CORE command
     */
    public function testSubCommandsCoreMultiwordCommand(): void
    {
        $this->exec('completion subcommands cache');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = [
            'list', 'clear', 'clear_all',
        ];
        foreach ($expected as $value) {
            $this->assertOutputContains($value);
        }
    }

    /**
     * test that subCommands with an existing plugin command returns the proper sub commands
     * when the command name is unique and the dot notation not mandatory
     */
    public function testSubCommandsPlugin(): void
    {
        $this->exec('completion subcommands welcome');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = 'say_hello';
        $this->assertOutputContains($expected);
    }

    /**
     * test that using the dot notation when not mandatory works to provide backward compatibility
     */
    public function testSubCommandsPluginDotNotationBackwardCompatibility(): void
    {
        $this->exec('completion subcommands test_plugin_two.welcome');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = 'say_hello';
        $this->assertOutputContains($expected);
    }

    /**
     * test that subCommands with an app command that is also defined in a plugin and without the prefix "app."
     * returns proper sub commands
     */
    public function testSubCommandsAppDuplicatePluginNoDot(): void
    {
        $this->exec('completion subcommands sample');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);
        $this->assertOutputContains('sub');
    }

    /**
     * test that subCommands with a plugin command that is also defined in the returns proper sub commands
     */
    public function testSubCommandsPluginDuplicateApp(): void
    {
        $this->exec('completion subcommands test_plugin.sample');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = 'sub';
        $this->assertOutputContains($expected);
    }

    /**
     * test that subcommands without arguments returns nothing
     */
    public function testSubCommandsNoArguments(): void
    {
        $this->exec('completion subcommands');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $this->assertOutputEmpty();
    }

    /**
     * test that subcommands with a nonexistent command returns nothing
     */
    public function testSubCommandsNonExistentCommand(): void
    {
        $this->exec('completion subcommands foo');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $this->assertOutputEmpty();
    }

    /**
     * test that subcommands returns the available subcommands for the given command
     */
    public function testSubCommands(): void
    {
        $this->exec('completion subcommands schema_cache');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $expected = 'build clear';
        $this->assertOutputContains($expected);
    }

    /**
     * test that help returns content
     */
    public function testHelp(): void
    {
        $this->exec('completion --help');
        $this->assertExitCode(CommandInterface::CODE_SUCCESS);

        $this->assertOutputContains('Output a list of available commands');
        $this->assertOutputContains('Output a list of available sub-commands');
    }
}
