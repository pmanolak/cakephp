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
 * @since         3.0.7
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Datasource;

use Cake\Core\Exception\CakeException;
use Cake\Datasource\RulesChecker;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;

/**
 * Tests the integration between the ORM and the domain checker
 */
class RulesCheckerTest extends TestCase
{
    /**
     * Test adding rule for update mode
     */
    public function testAddingRuleDeleteMode(): void
    {
        $entity = new Entity([
            'name' => 'larry',
        ]);

        $rules = new RulesChecker();
        $rules->addDelete(
            function () {
                return false;
            },
            'ruleName',
            ['errorField' => 'name'],
        );

        $this->assertTrue($rules->check($entity, RulesChecker::CREATE));
        $this->assertEmpty($entity->getErrors());
        $this->assertTrue($rules->check($entity, RulesChecker::UPDATE));
        $this->assertEmpty($entity->getErrors());

        $this->assertFalse($rules->check($entity, RulesChecker::DELETE));
        $this->assertEquals(['ruleName' => 'invalid'], $entity->getError('name'));
    }

    /**
     * Test adding rule for update mode
     */
    public function testAddingRuleUpdateMode(): void
    {
        $entity = new Entity([
            'name' => 'larry',
        ]);

        $rules = new RulesChecker();
        $rules->addUpdate(
            function () {
                return false;
            },
            'ruleName',
            ['errorField' => 'name'],
        );

        $this->assertTrue($rules->check($entity, RulesChecker::CREATE));
        $this->assertEmpty($entity->getErrors());
        $this->assertTrue($rules->check($entity, RulesChecker::DELETE));
        $this->assertEmpty($entity->getErrors());

        $this->assertFalse($rules->check($entity, RulesChecker::UPDATE));
        $this->assertEquals(['ruleName' => 'invalid'], $entity->getError('name'));
    }

    /**
     * Test adding rule for create mode
     */
    public function testAddingRuleCreateMode(): void
    {
        $entity = new Entity([
            'name' => 'larry',
        ]);

        $rules = new RulesChecker();
        $rules->addCreate(
            function () {
                return false;
            },
            'ruleName',
            ['errorField' => 'name'],
        );

        $this->assertTrue($rules->check($entity, RulesChecker::UPDATE));
        $this->assertEmpty($entity->getErrors());
        $this->assertTrue($rules->check($entity, RulesChecker::DELETE));
        $this->assertEmpty($entity->getErrors());

        $this->assertFalse($rules->check($entity, RulesChecker::CREATE));
        $this->assertEquals(['ruleName' => 'invalid'], $entity->getError('name'));
    }

    /**
     * Test adding rule with name
     */
    public function testAddingRuleWithName(): void
    {
        $entity = new Entity([
            'name' => 'larry',
        ]);

        $rules = new RulesChecker();
        $rules->add(
            function () {
                return false;
            },
            'ruleName',
            ['errorField' => 'name'],
        );

        $this->assertFalse($rules->check($entity, RulesChecker::CREATE));
        $this->assertEquals(['ruleName' => 'invalid'], $entity->getError('name'));
    }

    /**
     * Test that returned error messages work.
     */
    public function testAddWithErrorMessage(): void
    {
        $entity = new Entity([
            'name' => 'larry',
        ]);

        $rules = new RulesChecker();
        $rules->add(
            function () {
                return 'worst thing ever';
            },
            ['errorField' => 'name'],
        );

        $this->assertFalse($rules->check($entity, RulesChecker::CREATE));
        $this->assertEquals(['worst thing ever'], $entity->getError('name'));
    }

    /**
     * Test that returned error messages work.
     */
    public function testAddWithMessageOption(): void
    {
        $entity = new Entity([
            'name' => 'larry',
        ]);

        $rules = new RulesChecker();
        $rules->add(
            function () {
                return false;
            },
            ['message' => 'this is bad', 'errorField' => 'name'],
        );

        $this->assertFalse($rules->check($entity, RulesChecker::CREATE));
        $this->assertEquals(['this is bad'], $entity->getError('name'));
    }

    /**
     * Test that returned error messages work.
     */
    public function testAddWithoutFields(): void
    {
        $entity = new Entity([
            'name' => 'larry',
        ]);

        $rules = new RulesChecker();
        $rules->add(function () {
            return false;
        });

        $this->assertFalse($rules->check($entity, RulesChecker::CREATE));
        $this->assertEmpty($entity->getErrors());
    }

    public function testRemove(): void
    {
        $entity = new Entity([
            'name' => 'larry',
        ]);

        $rules = new RulesChecker();
        $rules->add(
            function () {
                return false;
            },
            'ruleName',
        );

        $this->assertFalse($rules->check($entity, RulesChecker::CREATE));

        $rules->remove('ruleName');
        $this->assertTrue($rules->check($entity, RulesChecker::CREATE));
    }

    public function testRemoveCreate(): void
    {
        $rules = new RulesChecker();
        $rules->addCreate(
            function () {
                return false;
            },
            'ruleName',
        );

        $entity = new Entity();
        $this->assertFalse($rules->check($entity, RulesChecker::CREATE));

        $rules->removeCreate('ruleName');
        $this->assertTrue($rules->check($entity, RulesChecker::CREATE));
    }

    public function testRemoveUpdate(): void
    {
        $rules = new RulesChecker();
        $rules->addUpdate(
            function () {
                return false;
            },
            'ruleName',
        );

        $entity = new Entity();
        $this->assertFalse($rules->check($entity, RulesChecker::UPDATE));

        $rules->removeUpdate('ruleName');
        $this->assertTrue($rules->check($entity, RulesChecker::UPDATE));
    }

    public function testRemoveDelete(): void
    {
        $rules = new RulesChecker();
        $rules->addDelete(
            function () {
                return false;
            },
            'ruleName',
        );

        $entity = new Entity();
        $this->assertFalse($rules->check($entity, RulesChecker::DELETE));

        $rules->removeDelete('ruleName');
        $this->assertTrue($rules->check($entity, RulesChecker::DELETE));
    }

    public function testAddDuplicateName(): void
    {
        $rules = new RulesChecker();
        $rules->add(fn() => false, 'myUniqueName');

        $this->expectException(CakeException::class);
        $rules->add(fn() => true, 'myUniqueName');
        $this->fail('Exception not thrown');
    }

    public function testAddCreateDuplicateName(): void
    {
        $rules = new RulesChecker();
        $rules->addCreate(fn() => false, 'myUniqueName');

        $this->expectException(CakeException::class);
        $rules->addCreate(fn() => true, 'myUniqueName');
        $this->fail('Exception not thrown');
    }

    public function testAddUpdateDuplicateName(): void
    {
        $rules = new RulesChecker();
        $rules->addUpdate(fn() => false, 'myUniqueName');

        $this->expectException(CakeException::class);
        $rules->addUpdate(fn() => true, 'myUniqueName');
        $this->fail('Exception not thrown');
    }

    public function testAddDeleteDuplicateName(): void
    {
        $rules = new RulesChecker();
        $rules->addDelete(fn() => false, 'myUniqueName');

        $this->expectException(CakeException::class);
        $rules->addDelete(fn() => true, 'myUniqueName');
        $this->fail('Exception not thrown');
    }
}
