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
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\TestSuite;

use Cake\TestSuite\TestCase;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * This class helps in indirectly testing the functionality of TestCase::assertHtml
 */
class AssertHtmlTest extends TestCase
{
    /**
     * Test whitespace after HTML tags
     */
    public function testAssertHtmlWhitespaceAfter(): void
    {
        $input = <<<HTML
<div class="wrapper">
    <h4 class="widget-title">Popular tags
        <i class="i-icon"></i>
    </h4>
</div>
HTML;
        $pattern = [
            'div' => ['class' => 'wrapper'],
            'h4' => ['class' => 'widget-title'], 'Popular tags',
            'i' => ['class' => 'i-icon'], '/i',
            '/h4',
            '/div',
        ];
        $this->assertHtml($pattern, $input);
    }

    /**
     * Test whitespace inside HTML tags
     */
    public function testAssertHtmlInnerWhitespace(): void
    {
        $input = <<<HTML
<div class="widget">
    <div class="widget-content">
        A custom widget
    </div>
</div>
HTML;
        $expected = [
            ['div' => ['class' => 'widget']],
            ['div' => ['class' => 'widget-content']],
            'A custom widget',
            '/div',
            '/div',
        ];
        $this->assertHtml($expected, $input);
    }

    /**
     * test assertHtml works with single and double quotes
     */
    public function testAssertHtmlQuoting(): void
    {
        $input = '<a href="/test.html" class="active">My link</a>';
        $pattern = [
            'a' => ['href' => '/test.html', 'class' => 'active'],
            'My link',
            '/a',
        ];
        $this->assertHtml($pattern, $input);

        $input = "<a href='/test.html' class='active'>My link</a>";
        $pattern = [
            'a' => ['href' => '/test.html', 'class' => 'active'],
            'My link',
            '/a',
        ];
        $this->assertHtml($pattern, $input);

        $input = "<a href='/test.html' class='active'>My link</a>";
        $pattern = [
            'a' => ['href' => 'preg:/.*\.html/', 'class' => 'active'],
            'My link',
            '/a',
        ];
        $this->assertHtml($pattern, $input);

        $input = '<span><strong>Text</strong></span>';
        $pattern = [
            '<span',
            '<strong',
            'Text',
            '/strong',
            '/span',
        ];
        $this->assertHtml($pattern, $input);

        $input = "<span class='active'><strong>Text</strong></span>";
        $pattern = [
            'span' => ['class'],
            '<strong',
            'Text',
            '/strong',
            '/span',
        ];
        $this->assertHtml($pattern, $input);
    }

    /**
     * Test that assertHtml runs quickly.
     */
    public function testAssertHtmlRuntimeComplexity(): void
    {
        $pattern = [
            'div' => [
                'attr1' => 'val1',
                'attr2' => 'val2',
                'attr3' => 'val3',
                'attr4' => 'val4',
                'attr5' => 'val5',
                'attr6' => 'val6',
                'attr7' => 'val7',
                'attr8' => 'val8',
            ],
            'My div',
            '/div',
        ];
        $input = '<div attr8="val8" attr6="val6" attr4="val4" attr2="val2"' .
            ' attr1="val1" attr3="val3" attr5="val5" attr7="val7" />' .
            'My div' .
            '</div>';
        $this->assertHtml($pattern, $input);
    }

    /**
     * test that assertHtml knows how to handle correct quoting.
     */
    public function testAssertHtmlQuotes(): void
    {
        $input = '<a href="/test.html" class="active">My link</a>';
        $pattern = [
            'a' => ['href' => '/test.html', 'class' => 'active'],
            'My link',
            '/a',
        ];
        $this->assertHtml($pattern, $input);

        $input = "<a href='/test.html' class='active'>My link</a>";
        $pattern = [
            'a' => ['href' => '/test.html', 'class' => 'active'],
            'My link',
            '/a',
        ];
        $this->assertHtml($pattern, $input);

        $input = "<a href='/test.html' class='active'>My link</a>";
        $pattern = [
            'a' => ['href' => 'preg:/.*\.html/', 'class' => 'active'],
            'My link',
            '/a',
        ];
        $this->assertHtml($pattern, $input);
    }

    /**
     * testNumericValuesInExpectationForAssertHtml
     */
    public function testNumericValuesInExpectationForAssertHtml(): void
    {
        $value = 220985;

        $input = '<p><strong>' . $value . '</strong></p>';
        $pattern = [
            '<p',
                '<strong',
                    $value,
                '/strong',
            '/p',
        ];
        $this->assertHtml($pattern, $input);

        $input = '<p><strong>' . $value . '</strong></p><p><strong>' . $value . '</strong></p>';
        $pattern = [
            '<p',
                '<strong',
                    $value,
                '/strong',
            '/p',
            '<p',
                '<strong',
                    $value,
                '/strong',
            '/p',
        ];
        $this->assertHtml($pattern, $input);

        $input = '<p><strong>' . $value . '</strong></p><p id="' . $value . '"><strong>' . $value . '</strong></p>';
        $pattern = [
            '<p',
                '<strong',
                    $value,
                '/strong',
            '/p',
            'p' => ['id' => $value],
                '<strong',
                    $value,
                '/strong',
            '/p',
        ];
        $this->assertHtml($pattern, $input);
    }

    /**
     * test assertions fail when attributes are wrong.
     */
    public function testBadAssertHtmlInvalidAttribute(): void
    {
        $input = '<a href="/test.html" class="active">My link</a>';
        $pattern = [
            'a' => ['hRef' => '/test.html', 'clAss' => 'active'],
            'My link2',
            '/a',
        ];
        try {
            $this->assertHtml($pattern, $input);
            $this->fail('Assertion should fail');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString(
                'Attribute did not match. Was expecting Attribute `clAss` == `active`',
                $e->getMessage(),
            );
        }
    }

    /**
     * test assertion failure on incomplete HTML
     */
    public function testBadAssertHtmlMissingTags(): void
    {
        $input = '<a href="/test.html" class="active">My link</a>';
        $pattern = [
            '<a' => ['href' => '/test.html', 'class' => 'active'],
            'My link',
            '/a',
        ];
        try {
            $this->assertHtml($pattern, $input);
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString(
                'Item #1 / regex #0 failed: Open <a tag',
                $e->getMessage(),
            );
        }
    }
}
