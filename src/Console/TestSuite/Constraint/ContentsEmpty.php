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
 * @since         3.7.0
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Console\TestSuite\Constraint;

/**
 * ContentsEmpty
 *
 * @internal
 */
class ContentsEmpty extends ContentsBase
{
    /**
     * Checks if contents are empty
     *
     * @param mixed $other Expected
     * @return bool
     */
    public function matches(mixed $other): bool
    {
        return $this->contents === '';
    }

    /**
     * Assertion message
     *
     * @return string
     */
    public function toString(): string
    {
        return sprintf('%s is empty.', $this->output);
    }

    /**
     * Overwrites the descriptions so we can remove the automatic "expected" message
     *
     * @param mixed $other Value
     * @return string
     */
    protected function failureDescription(mixed $other): string
    {
        return $this->toString();
    }

    /**
     * @inheritDoc
     */
    protected function additionalFailureDescription(mixed $other): string
    {
        return sprintf("actual result:\n%s", $this->contents);
    }
}

// phpcs:disable
class_alias(
    'Cake\Console\TestSuite\Constraint\ContentsEmpty',
    'Cake\TestSuite\Constraint\Console\ContentsEmpty'
);
// phpcs:enable
