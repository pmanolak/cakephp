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
namespace Cake\I18n;

use Cake\Core\Exception\CakeException;
use InvalidArgumentException;
use Locale;

/**
 * Utility class used to determine the plural number to be used for a variable
 * base on the locale.
 *
 * @internal
 */
class PluralRules
{
    /**
     * A map of locale => plurals group used to determine
     * which plural rules apply to the language
     *
     * @var array<string, int>
     */
    protected static array $_rulesMap = [
        'af' => 1,
        'am' => 2,
        'ar' => 13,
        'az' => 1,
        'be' => 3,
        'bg' => 1,
        'bh' => 2,
        'bn' => 1,
        'bo' => 0,
        'bs' => 3,
        'ca' => 1,
        'cs' => 4,
        'cy' => 14,
        'da' => 1,
        'de' => 1,
        'dz' => 0,
        'el' => 1,
        'en' => 1,
        'eo' => 1,
        'es' => 17,
        'et' => 1,
        'eu' => 1,
        'fa' => 1,
        'fi' => 1,
        'fil' => 2,
        'fo' => 1,
        'fr' => 16,
        'fur' => 1,
        'fy' => 1,
        'ga' => 5,
        'gl' => 1,
        'gu' => 1,
        'gun' => 2,
        'ha' => 1,
        'he' => 1,
        'hi' => 2,
        'hr' => 3,
        'hu' => 1,
        'id' => 0,
        'is' => 15,
        'it' => 17,
        'ja' => 0,
        'jv' => 0,
        'ka' => 0,
        'km' => 0,
        'kn' => 0,
        'ko' => 0,
        'ku' => 1,
        'lb' => 1,
        'ln' => 2,
        'lt' => 6,
        'lv' => 10,
        'mg' => 2,
        'mk' => 8,
        'ml' => 1,
        'mn' => 1,
        'mr' => 1,
        'ms' => 0,
        'mt' => 9,
        'nah' => 1,
        'nb' => 1,
        'ne' => 1,
        'nl' => 1,
        'nn' => 1,
        'no' => 1,
        'nso' => 2,
        'om' => 1,
        'or' => 1,
        'pa' => 1,
        'pap' => 1,
        'pl' => 11,
        'ps' => 1,
        'pt_PT' => 17,
        'pt' => 16,
        'ro' => 12,
        'ru' => 3,
        'sk' => 4,
        'sl' => 7,
        'so' => 1,
        'sq' => 1,
        'sr' => 3,
        'sv' => 1,
        'sw' => 1,
        'ta' => 1,
        'te' => 1,
        'th' => 0,
        'ti' => 2,
        'tk' => 1,
        'tr' => 1,
        'uk' => 3,
        'ur' => 1,
        'vi' => 0,
        'wa' => 2,
        'zh' => 0,
        'zu' => 1,
    ];

    /**
     * Returns the plural form number for the passed locale corresponding
     * to the countable provided in $n.
     *
     * @param string $locale The locale to get the rule calculated for.
     * @param int $n The number to apply the rules to.
     * @return int The plural rule number that should be used.
     * @link https://php-gettext.github.io/Languages/#47
     */
    public static function calculate(string $locale, int $n): int
    {
        $locale = Locale::canonicalize($locale);

        if ($locale === null) {
            throw new InvalidArgumentException('Invalid locale provided');
        }

        if (!isset(static::$_rulesMap[$locale])) {
            $locale = explode('_', $locale)[0];
        }

        if (!isset(static::$_rulesMap[$locale])) {
            return 0;
        }

        return match (static::$_rulesMap[$locale]) {
            0 => 0,
            1 => $n === 1 ? 0 : 1,
            2 => $n > 1 ? 1 : 0,
            3 => $n % 10 === 1 && $n % 100 !== 11 ? 0 :
                    (($n % 10 >= 2 && $n % 10 <= 4) && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2),
            4 => $n === 1 ? 0 :
                    ($n >= 2 && $n <= 4 ? 1 : 2),
            5 => $n === 1 ? 0 :
                    ($n === 2 ? 1 : ($n < 7 ? 2 : ($n < 11 ? 3 : 4))),
            6 => $n % 10 === 1 && $n % 100 !== 11 ? 0 :
                    ($n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2),
            7 => $n % 100 === 1 ? 1 :
                    ($n % 100 === 2 ? 2 : ($n % 100 === 3 || $n % 100 === 4 ? 3 : 0)),
            8 => $n % 10 === 1 ? 0 : ($n % 10 === 2 ? 1 : 2),
            9 => $n === 1 ? 0 :
                    ($n === 0 || ($n % 100 > 0 && $n % 100 <= 10) ? 1 :
                    ($n % 100 > 10 && $n % 100 < 20 ? 2 : 3)),
            10 => $n % 10 === 1 && $n % 100 !== 11 ? 0 : ($n !== 0 ? 1 : 2),
            11 => $n === 1 ? 0 :
                    ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2),
            12 => $n === 1 ? 0 :
                    ($n === 0 || $n % 100 > 0 && $n % 100 < 20 ? 1 : 2),
            13 => $n === 0 ? 0 :
                    ($n === 1 ? 1 :
                    ($n === 2 ? 2 :
                    ($n % 100 >= 3 && $n % 100 <= 10 ? 3 :
                    ($n % 100 >= 11 ? 4 : 5)))),
            14 => $n === 1 ? 0 :
                    ($n === 2 ? 1 :
                    ($n !== 8 && $n !== 11 ? 2 : 3)),
            15 => $n % 10 !== 1 || $n % 100 === 11 ? 1 : 0,
            16 => $n === 0 || $n === 1 ? 0 : ($n % 1000000 === 0 ? 1 : 2),
            17 => $n === 1 ? 0 : ($n !== 0 && $n % 1000000 === 0 ? 1 : 2),
            default => throw new CakeException('Unable to find plural rule number.'),
        };
    }
}
