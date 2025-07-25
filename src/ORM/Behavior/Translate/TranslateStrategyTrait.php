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
 * @since         4.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ORM\Behavior\Translate;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\I18n;
use Cake\ORM\Marshaller;
use Cake\ORM\Table;

/**
 * Contains common code needed by TranslateBehavior strategy classes.
 */
trait TranslateStrategyTrait
{
    /**
     * Table instance
     *
     * @var \Cake\ORM\Table
     */
    protected Table $table;

    /**
     * The locale name that will be used to override fields in the bound table
     * from the translations table
     *
     * @var string|null
     */
    protected ?string $locale = null;

    /**
     * Instance of Table responsible for translating
     *
     * @var \Cake\ORM\Table
     */
    protected Table $translationTable;

    /**
     * Return translation table instance.
     *
     * @return \Cake\ORM\Table
     */
    public function getTranslationTable(): Table
    {
        return $this->translationTable;
    }

    /**
     * Sets the locale to be used.
     *
     * When fetching records, the content for the locale set via this method,
     * and likewise when saving data, it will save the data in that locale.
     *
     * Note that in case an entity has a `_locale` property set, that locale
     * will win over the locale set via this method (and over the globally
     * configured one for that matter)!
     *
     * @param string|null $locale The locale to use for fetching and saving
     *   records. Pass `null` in order to unset the current locale, and to make
     *   the behavior falls back to using the globally configured locale.
     * @return $this
     */
    public function setLocale(?string $locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Returns the current locale.
     *
     * If no locale has been explicitly set via `setLocale()`, this method will return
     * the currently configured global locale excluding any options set after @.
     *
     * @return string
     * @see \Cake\I18n\I18n::getLocale()
     * @see \Cake\ORM\Behavior\TranslateBehavior::setLocale()
     */
    public function getLocale(): string
    {
        return $this->locale ?: explode('@', I18n::getLocale())[0];
    }

    /**
     * Unset empty translations to avoid persistence.
     *
     * Should only be called if $this->_config['allowEmptyTranslations'] is false.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to check for empty translations fields inside.
     * @return void
     */
    protected function unsetEmptyFields(EntityInterface $entity): void
    {
        if (!$entity->has('_translations')) {
            return;
        }

        /** @var array<\Cake\Datasource\EntityInterface> $translations */
        $translations = $entity->get('_translations');
        foreach ($translations as $locale => $translation) {
            $fields = $translation->extract($this->_config['fields'], false);
            foreach ($fields as $field => $value) {
                if ($value === null || $value === '') {
                    $translation->unset($field);
                }
            }

            $translation = $translation->extract($this->_config['fields']);

            // If now, the current locale property is empty,
            // unset it completely.
            if (array_filter($translation) === []) {
                unset($translations[$locale]);
            }
        }

        // If now, the whole $translations is empty, unset _translations property completely
        if ($translations === []) {
            $entity->unset('_translations');
        } else {
            $entity->set('_translations', $translations);
        }
    }

    /**
     * Build a set of properties that should be included in the marshaling process.

     * Add in `_translations` marshaling handlers. You can disable marshaling
     * of translations by setting `'translations' => false` in the options
     * provided to `Table::newEntity()` or `Table::patchEntity()`.
     *
     * @param \Cake\ORM\Marshaller $marshaller The marshaler of the table the behavior is attached to.
     * @param array $map The property map being built.
     * @param array<string, mixed> $options The options array used in the marshaling call.
     * @return array A map of `[property => callable]` of additional properties to marshal.
     */
    public function buildMarshalMap(Marshaller $marshaller, array $map, array $options): array
    {
        if (isset($options['translations']) && !$options['translations']) {
            return [];
        }

        return [
            '_translations' => function ($value, EntityInterface $entity) use ($marshaller, $options) {
                if (!is_array($value)) {
                    return null;
                }

                /** @var array<string, \Cake\Datasource\EntityInterface> $translations */
                $translations = $entity->has('_translations') ? (array)$entity->get('_translations') : [];

                $options['validate'] = $this->_config['validator'];
                $errors = [];
                foreach ($value as $language => $fields) {
                    if (!isset($translations[$language])) {
                        $translations[$language] = $this->table->newEmptyEntity();
                    }
                    $marshaller->merge($translations[$language], $fields, $options);

                    $translationErrors = $translations[$language]->getErrors();
                    if ($translationErrors) {
                        $errors[$language] = $translationErrors;
                    }
                }

                // Set errors into the root entity, so validation errors match the original form data position.
                if ($errors) {
                    $entity->setErrors(['_translations' => $errors]);
                }

                return $translations;
            },
        ];
    }

    /**
     * Unsets the temporary `_i18n` property after the entity has been saved
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event The beforeSave event that was fired
     * @param \Cake\Datasource\EntityInterface $entity The entity that is going to be saved
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity): void
    {
        $entity->unset('_i18n');
    }
}
