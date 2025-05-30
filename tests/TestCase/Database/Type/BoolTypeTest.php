<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.1.7
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Database\Type;

use Cake\Database\Driver;
use Cake\Database\TypeFactory;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use PDO;

/**
 * Test for the Boolean type.
 */
class BoolTypeTest extends TestCase
{
    /**
     * @var \Cake\Database\Type\BoolType
     */
    protected $type;

    /**
     * @var \Cake\Database\Driver
     */
    protected $driver;

    /**
     * Setup
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->type = TypeFactory::build('boolean');
        $this->driver = $this->getMockBuilder(Driver::class)->getMock();
    }

    /**
     * Test converting to database format
     */
    public function testToDatabase(): void
    {
        $this->assertNull($this->type->toDatabase(null, $this->driver));
        $this->assertTrue($this->type->toDatabase(true, $this->driver));
        $this->assertFalse($this->type->toDatabase(false, $this->driver));
        $this->assertTrue($this->type->toDatabase(1, $this->driver));
        $this->assertFalse($this->type->toDatabase(0, $this->driver));
        $this->assertTrue($this->type->toDatabase('1', $this->driver));
        $this->assertFalse($this->type->toDatabase('0', $this->driver));
    }

    /**
     * Test converting an array to boolean results in an exception
     */
    public function testToDatabaseInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->type->toDatabase([1, 2], $this->driver);
    }

    /**
     * Tests that passing an invalid value will throw an exception
     */
    public function testToDatabaseInvalidArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->type->toDatabase([1, 2, 3], $this->driver);
    }

    /**
     * Test converting string booleans to PHP values.
     */
    public function testToPHP(): void
    {
        $this->assertNull($this->type->toPHP(null, $this->driver));
        $this->assertTrue($this->type->toPHP(1, $this->driver));
        $this->assertTrue($this->type->toPHP('1', $this->driver));
        $this->assertTrue($this->type->toPHP('TRUE', $this->driver));
        $this->assertTrue($this->type->toPHP('true', $this->driver));
        $this->assertTrue($this->type->toPHP(true, $this->driver));

        $this->assertFalse($this->type->toPHP(0, $this->driver));
        $this->assertFalse($this->type->toPHP('0', $this->driver));
        $this->assertFalse($this->type->toPHP('FALSE', $this->driver));
        $this->assertFalse($this->type->toPHP('false', $this->driver));
        $this->assertFalse($this->type->toPHP(false, $this->driver));
    }

    /**
     * Test converting string booleans to PHP values.
     */
    public function testManyToPHP(): void
    {
        $values = [
            'a' => null,
            'b' => 'true',
            'c' => 'TRUE',
            'd' => 'false',
            'e' => 'FALSE',
            'f' => '0',
            'g' => '1',
            'h' => true,
            'i' => false,
        ];
        $expected = [
            'a' => null,
            'b' => true,
            'c' => true,
            'd' => false,
            'e' => false,
            'f' => false,
            'g' => true,
            'h' => true,
            'i' => false,
        ];
        $this->assertEquals(
            $expected,
            $this->type->manyToPHP($values, array_keys($values), $this->driver),
        );
    }

    /**
     * Test marshalling booleans
     */
    public function testMarshal(): void
    {
        $this->assertNull($this->type->marshal(null));
        $this->assertTrue($this->type->marshal(true));
        $this->assertTrue($this->type->marshal(1));
        $this->assertTrue($this->type->marshal('1'));
        $this->assertTrue($this->type->marshal('true'));
        $this->assertTrue($this->type->marshal('on'));

        $this->assertFalse($this->type->marshal(false));
        $this->assertFalse($this->type->marshal('false'));
        $this->assertFalse($this->type->marshal('0'));
        $this->assertFalse($this->type->marshal(0));
        $this->assertFalse($this->type->marshal('off'));
        $this->assertNull($this->type->marshal(''));
        $this->assertNull($this->type->marshal('not empty'));
        $this->assertNull($this->type->marshal(['2', '3']));
    }

    /**
     * Test converting booleans to PDO types.
     */
    public function testToStatement(): void
    {
        $this->assertSame(PDO::PARAM_NULL, $this->type->toStatement(null, $this->driver));
        $this->assertSame(PDO::PARAM_BOOL, $this->type->toStatement(true, $this->driver));
        $this->assertSame(PDO::PARAM_BOOL, $this->type->toStatement(false, $this->driver));
    }
}
