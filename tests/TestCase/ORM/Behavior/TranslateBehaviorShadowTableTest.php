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
namespace Cake\Test\TestCase\ORM\Behavior;

use Cake\Database\Driver\Postgres;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\ExpressionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\I18n;
use Cake\ORM\Behavior\Translate\ShadowTableStrategy;
use Cake\ORM\Behavior\TranslateBehavior;
use Cake\ORM\Entity;
use Cake\Utility\Hash;
use TestApp\Model\Entity\TranslateArticle;
use TestApp\Model\Entity\TranslateBakedArticle;

/**
 * TranslateBehavior test case
 */
class TranslateBehaviorShadowTableTest extends TranslateBehaviorEavTest
{
    protected array $fixtures = [
        'core.Articles',
        'core.Authors',
        'core.Comments',
        'core.Tags',
        'core.TagsTranslations',
        'core.ArticlesTags',
        'core.SpecialTags',
        'core.Sections',
        'core.ArticlesTranslations',
        'core.ArticlesMoreTranslations',
        'core.AuthorsTranslations',
        'core.CommentsTranslations',
        'core.TagsShadowTranslations',
        'core.SpecialTagsTranslations',
        'core.SectionsTranslations',
    ];

    /**
     * setUpBeforeClass
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TranslateBehavior::setDefaultStrategyClass(ShadowTableStrategy::class);
    }

    /**
     * tearDownAfterClass
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        TranslateBehavior::setDefaultStrategyClass(ShadowTableStrategy::class);
    }

    /**
     * Check things are setup correctly by default
     *
     * The hasOneAlias is used for the has-one translation, the translationTable is used
     * with findTranslations
     */
    public function testDefaultAliases(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->getTable();
        $table->addBehavior('Translate');

        $config = $table->behaviors()->get('Translate')->getStrategy()->getConfig();
        $wantedKeys = [
            'translationTable',
            'mainTableAlias',
            'hasOneAlias',
        ];
        $config = array_intersect_key($config, array_flip($wantedKeys));
        $expected = [
            'translationTable' => 'ArticlesTranslations',
            'mainTableAlias' => 'Articles',
            'hasOneAlias' => 'ArticlesTranslation',
        ];
        $this->assertEquals($expected, $config, 'Used aliases should match the main table object');

        $this->_testFind();
    }

    /**
     * Check things are setup correctly by default for plugin models
     */
    public function testDefaultPluginAliases(): void
    {
        $table = $this->getTableLocator()->get('SomeRandomPlugin.Articles');

        $table->getTable();
        $table->addBehavior('Translate');

        $config = $table->behaviors()->get('Translate')->getStrategy()->getConfig();
        $wantedKeys = [
            'translationTable',
            'mainTableAlias',
            'hasOneAlias',
        ];
        $config = array_intersect_key($config, array_flip($wantedKeys));
        $expected = [
            'translationTable' => 'SomeRandomPlugin.ArticlesTranslations',
            'mainTableAlias' => 'Articles',
            'hasOneAlias' => 'ArticlesTranslation',
        ];
        $this->assertEquals($expected, $config, 'Used aliases should match the main table object');

        $exists = $this->getTableLocator()->exists('SomeRandomPlugin.ArticlesTranslations');
        $this->assertTrue($exists, 'The behavior should have populated this key with a table object');

        $translationTable = $this->getTableLocator()->get('SomeRandomPlugin.ArticlesTranslations');
        $this->assertSame(
            'SomeRandomPlugin.ArticlesTranslations',
            $translationTable->getRegistryAlias(),
            'It should be a different object to the one in the no-plugin prefix',
        );

        $this->_testFind('SomeRandomPlugin.Articles');
    }

    public function testComplexLocales(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', ['fields' => ['title', 'body']]);

        I18n::setLocale('fr@currency=EUR');
        $this->assertSame('fr', $table->getLocale());

        $table->setLocale('en_US');
        $this->assertSame('en_US', $table->getLocale());
    }

    /**
     * testAutoReferenceName
     *
     * The parent test is EAV specific. Test that the config reflects the referenceName -
     * which is used to determine the the translation table/association name only in the
     * shadow translate behavior
     */
    public function testAutoReferenceName(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->getTable();
        $table->addBehavior('Translate');

        $config = $table->behaviors()->get('Translate')->getStrategy()->getConfig();
        $wantedKeys = [
            'translationTable',
            'mainTableAlias',
            'hasOneAlias',
        ];

        $config = array_intersect_key($config, array_flip($wantedKeys));
        $expected = [
            'translationTable' => 'ArticlesTranslations',
            'mainTableAlias' => 'Articles',
            'hasOneAlias' => 'ArticlesTranslation',
        ];
        $this->assertEquals($expected, $config, 'The translationTable key should be derived from referenceName');
    }

    /**
     * testChangingReferenceName
     *
     * The parent test is EAV specific. Test that the config reflects the referenceName -
     * which is used to determine the the translation table/association name only in the
     * shadow translate behavior
     */
    public function testChangingReferenceName(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->getTable();
        $table->addBehavior(
            'Translate',
            ['referenceName' => 'Posts'],
        );

        $config = $table->behaviors()->get('Translate')->getStrategy()->getConfig();
        $wantedKeys = [
            'translationTable',
            'mainTableAlias',
            'hasOneAlias',
        ];

        $config = array_intersect_key($config, array_flip($wantedKeys));
        $expected = [
            'translationTable' => 'PostsTranslations',
            'mainTableAlias' => 'Articles',
            'hasOneAlias' => 'ArticlesTranslation',
        ];
        $this->assertEquals($expected, $config, 'The translationTable key should be derived from referenceName');
    }

    /**
     * Allow usage without specifying fields explicitly
     *
     * Fields are only detected when necessary, one of those times is a fine with fields.
     */
    public function testAutoFieldDetection(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');

        $table->setLocale('eng');
        $table->find()->select(['title'])->first();

        $expected = ['title', 'body'];
        $result = $table->behaviors()->get('Translate')->getStrategy()->getConfig('fields');
        $this->assertSame(
            $expected,
            $result,
            'If no fields are specified, they should be derived from the schema',
        );
    }

    /**
     * testTranslationTableConfig
     */
    public function testTranslationTableConfig(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');

        $exists = $this->getTableLocator()->exists('ArticlesTranslations');
        $this->assertTrue($exists, 'The table registry should have an object in this key now');

        $translationTable = $this->getTableLocator()->get('ArticlesTranslations');
        $this->assertSame('articles_translations', $translationTable->getTable());
        $this->assertSame('ArticlesTranslations', $translationTable->getAlias());
    }

    /**
     * Only join translations when necessary
     *
     * By inspecting the sql generated, verify that if there is a need for the translation
     * table to be included in the query it is present, and when there is no clear need -
     * that it is not.
     */
    public function testNoUnnecessaryJoins(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');

        $query = $table->find();
        $this->assertStringNotContainsString(
            'articles_translations',
            $query->sql(),
            "The default locale doesn't need a join",
        );

        $table->setLocale('eng');

        $query = $table->find()->select(['id']);
        $this->assertStringNotContainsString(
            'articles_translations',
            $query->sql(),
            'No translated fields, nothing to do',
        );

        $query = $table->find()->select(['Other.title']);
        $this->assertStringNotContainsString(
            'articles_translations',
            $query->sql(),
            "Other isn't the table class with the translate behavior, nothing to do",
        );
    }

    /**
     * Join when translations are necessary
     */
    public function testNecessaryJoinsSelect(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $table->setLocale('eng');

        $query = $table->find();
        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'No fields specified, means select all fields - translated included',
        );

        $query = $table->find()->select(['title']);
        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'Selecting a translated field should join the translations table',
        );

        $query = $table->find()->select(['Articles.title']);
        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'Selecting an aliased translated field should join the translations table',
        );
    }

    /**
     * Join when translations are necessary
     */
    public function testNecessaryJoinsWhere(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $table->setLocale('eng');

        $query = $table->find()->select(['id'])->where(['title' => 'First Article']);
        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'If the where clause includes a translated field - a join is required',
        );
    }

    /**
     * Join when translations are necessary
     */
    public function testNecessaryJoinsConfig(): void
    {
        $table = $this->getTableLocator()->get('Articles');

        $table->addBehavior('Translate', [
            'onlyTranslated' => true,
        ]);
        $table->setLocale('eng');

        $query = $table->find()->select(['id'])->disableAutoFields();
        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'Enabling `onlyTranslated` should join the translations table',
        );

        $table
            ->removeBehavior('Translate')
            ->addBehavior('Translate');
        $table->setLocale('eng');

        $query = $table->find('all', filterByCurrentLocale: true)->select(['id'])->disableAutoFields();
        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'Enabling `filterByCurrentLocale` should join the translations table',
        );
    }

    /**
     * testTraversingWhereClauseWithNonStringField
     */
    public function testTraversingWhereClauseWithNonStringField(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $table->setLocale('eng');

        $query = $table->find()->select()->where(function (ExpressionInterface $exp) {
            return $exp->lt(new QueryExpression('1'), 50);
        });

        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'Do not try to use non string fields when traversing "where" clause',
        );
    }

    /**
     * Join when translations are necessary
     */
    public function testNecessaryJoinsOrder(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $table->setLocale('eng');

        $query = $table->find()->select(['id'])->orderBy(['title' => 'desc']);
        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'If the order clause includes a translated field - a join is required',
        );

        $query = $table->find();
        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'No fields means auto-fields - a join is required',
        );
    }

    /**
     * Setup a contrived self join and make sure both records are translated
     *
     * Different locales are used on each table object just to make any resulting
     * confusion easier to identify as neither the original or translated values
     * overlap between the two records.
     */
    public function testSelfJoin(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $table->setLocale('eng');

        $table->belongsTo('Copy', ['className' => 'Articles', 'foreignKey' => 'author_id']);
        $table->Copy->addBehavior('Translate');
        $table->Copy->setLocale('deu');

        $query = $table->find()
            ->where(['Articles.id' => 3])
            ->contain('Copy');

        $result = $query->first()->toArray();
        $expected = [
            'id' => 3,
            'author_id' => 1,
            'title' => 'Title #3',
            'body' => 'Content #3',
            'published' => 'Y',
            'copy' => [
                'id' => 1,
                'author_id' => 1,
                'title' => 'Titel #1',
                'body' => 'Inhalt #1',
                'published' => 'Y',
                '_locale' => 'deu',
            ],
            '_locale' => 'eng',
        ];
        $this->assertEquals(
            $expected,
            $result,
            'The copy record should also be translated',
        );
    }

    /**
     * Verify it is not necessary for a translated field to exist in the master table
     */
    public function testVirtualTranslationField(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', [
            'translationTableAlias' => 'ArticlesMoreTranslations',
            'translationTable' => 'articles_more_translations',
        ]);

        $table->setLocale('eng');
        $results = $table->find()->all()->combine('title', 'subtitle', 'id')->toArray();
        $expected = [
            1 => ['Title #1' => 'SubTitle #1'],
            2 => ['Title #2' => 'SubTitle #2'],
            3 => ['Title #3' => 'SubTitle #3'],
        ];
        $this->assertSame($expected, $results);
    }

    /**
     * Tests that after deleting a translated entity, all translations are also removed
     */
    public function testDelete(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $article = $table->find()->first();
        $this->assertTrue($table->delete($article));

        $translations = $this->getTableLocator()->get('ArticlesTranslations')->find()
            ->where(['id' => $article->id])
            ->count();
        $this->assertSame(0, $translations);
    }

    /**
     * testNoAmbiguousFields
     */
    public function testNoAmbiguousFields(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $table->setLocale('eng');

        $article = $table->find('all')
            ->select(['id'])
            ->toArray();

        $this->assertNotNull($article, "There will be an exception if there's ambiguous sql");

        $article = $table->find('all')
            ->select(['title'])
            ->toArray();

        $this->assertNotNull($article, "There will be an exception if there's ambiguous sql");
    }

    /**
     * testNoAmbiguousConditions
     */
    public function testNoAmbiguousConditions(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $table->setLocale('eng');

        $article = $table->find('all')
            ->where(['id' => 1])
            ->toArray();

        $this->assertNotNull($article, "There will be an exception if there's ambiguous sql");

        $article = $table->find('all')
            ->where(['title' => 1])
            ->toArray();

        $this->assertNotNull($article, "There will be an exception if there's ambiguous sql");
    }

    /**
     * testNoAmbiguousOrder
     */
    public function testNoAmbiguousOrderBy(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $table->setLocale('eng');

        $article = $table->find('all')
            ->orderBy(['id' => 'desc'])
            ->enableHydration(false)
            ->toArray();

        $this->assertSame([3, 2, 1], Hash::extract($article, '{n}.id'));

        $article = $table->find('all')
            ->orderBy(['title' => 'asc'])
            ->enableHydration(false)
            ->toArray();

        $expected = ['Title #1', 'Title #2', 'Title #3'];
        $this->assertSame($expected, Hash::extract($article, '{n}.title'));
    }

    /**
     * If results are unhydrated, it should still work
     */
    public function testUnhydratedResults(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');

        $result = $table
            ->find('translations')
            ->enableHydration(false)
            ->first();
        $this->assertArrayHasKey('title', $result);
    }

    /**
     * A find containing another association should act the same whether translated or not
     */
    public function testFindWithAssociations(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->belongsTo('Authors');

        $table->addBehavior('Translate');
        $table->setLocale('eng');

        $query = $table
            ->find('translations')
            ->where(['Articles.id' => 1])
            ->contain(['Authors']);
        $this->assertStringContainsString(
            'articles_translations',
            $query->sql(),
            'There should be a join to the translations table',
        );

        $result = $query->firstOrFail();

        $this->assertNotNull($result->author, 'There should be an author for article 1.');
        $expected = [
            'id' => 1,
            'name' => 'mariano',
        ];
        $this->assertSame($expected, $result->author->toArray());

        $this->assertNotEmpty($result->_translations, "Translations can't be empty.");
    }

    /**
     * Test that when finding BTM associations, the contained BTM data is also translated.
     */
    public function testFindWithBTMAssociations(): void
    {
        $Articles = $this->getTableLocator()->get('Articles');
        $Tags = $this->getTableLocator()->get('Tags');

        // This required because there's already a fixture for tags_translations which isn't a shadow table
        $this->getTableLocator()->get('TagsTranslations', [
            'className' => 'TagsShadowTranslations',
        ]);
        $this->getTableLocator()->get('TagsTranslation', [
            'className' => 'TagsShadowTranslations',
        ]);

        $Articles->addBehavior('Translate');
        $Tags->addBehavior('Translate');

        $Articles->setLocale('deu');
        $Tags->setLocale('deu');

        $Articles->belongsToMany('Tags');

        $query = $Articles
            ->find()
            ->where(['Articles.id' => 1])
            ->contain(['Tags']);

        $result = $query->firstOrFail();

        $this->assertCount(2, $result->tags, 'There should be two translated tags.');

        $expected = [
            'id' => 1,
            'name' => 'tag1 in deu',
            '_locale' => 'deu',
            '_joinData' => [
                'tag_id' => 1,
                'article_id' => 1,
            ],
        ];
        $record = $result->tags[0]->toArray();
        unset($record['description'], $record['created']);
        $this->assertEquals($expected, $record);

        $expected = [
            'id' => 2,
            'name' => 'tag2 in deu',
            '_locale' => 'deu',
            '_joinData' => [
                'tag_id' => 2,
                'article_id' => 1,
            ],
        ];
        $record = $result->tags[1]->toArray();
        unset($record['description'], $record['created']);
        $this->assertEquals($expected, $record);
    }

    /**
     * A find containing a translated association doesn't error on incomplete data
     */
    public function testFindTranslationsAssociatedContain(): void
    {
        $comments = $this->fetchTable('Comments');
        $comments->belongsTo('Articles');

        $articles = $this->fetchTable('Articles');

        // Remove all articles so we have a missing record.
        $articles->deleteAll('1=1');

        $articles->addBehavior('Translate');
        $articles->setLocale('eng');

        $query = $comments
            ->find()
            ->where(['Comments.id' => 1])
            ->contain([
                'Articles' => function ($q) {
                    return $q->find('translations');
                }]);
        $record = $query->firstOrFail();
        $this->assertNull($record->article);
    }

    /**
     * Tests that it is possible to get all translated fields at once
     */
    public function testFindTranslations(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', ['fields' => ['title', 'body']]);
        $results = $table->find('translations', locales: ['eng', 'deu', 'cze', 'spa']);
        $expected = [
            [
                'eng' => ['title' => 'Title #1', 'body' => 'Content #1', 'locale' => 'eng'],
                'deu' => ['title' => 'Titel #1', 'body' => 'Inhalt #1', 'locale' => 'deu'],
                'cze' => ['title' => 'Titulek #1', 'body' => 'Obsah #1', 'locale' => 'cze'],
                'spa' => ['title' => 'First Article', 'body' => 'Contenido #1', 'locale' => 'spa'],
            ],
            [
                'eng' => ['title' => 'Title #2', 'body' => 'Content #2', 'locale' => 'eng'],
                'deu' => ['title' => 'Titel #2', 'body' => 'Inhalt #2', 'locale' => 'deu'],
                'cze' => ['title' => 'Titulek #2', 'body' => 'Obsah #2', 'locale' => 'cze'],
            ],
            [
                'eng' => ['title' => 'Title #3', 'body' => 'Content #3', 'locale' => 'eng'],
                'deu' => ['title' => 'Titel #3', 'body' => 'Inhalt #3', 'locale' => 'deu'],
                'cze' => ['title' => 'Titulek #3', 'body' => 'Obsah #3', 'locale' => 'cze'],
            ],
        ];

        $translations = $this->_extractTranslations($results);
        $this->assertEquals($expected, $translations->toArray());
        $expected = [
            1 => ['First Article' => 'First Article Body'],
            2 => ['Second Article' => 'Second Article Body'],
            3 => ['Third Article' => 'Third Article Body'],
        ];

        $grouped = $results->all()->combine('title', 'body', 'id');
        $this->assertEquals($expected, $grouped->toArray());

        $entity = $table->newEntity(['title' => 'Fourth Title']);
        $table->save($entity);

        $expected = [[]];
        $result = $table->find('translations')->where(['Articles.id' => $entity->id])->all();
        $this->assertEquals($expected, $this->_extractTranslations($result)->toArray());

        $entity = $result->first();
        $this->assertSame('Fourth Title', $entity->title);
    }

    /**
     * By default empty translations should be honored
     */
    public function testEmptyTranslationsDefaultBehavior(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');
        $table->setLocale('zzz');
        $result = $table->get(1);

        $this->assertSame('', $result->title, 'The empty translation should be used');
        $this->assertSame('', $result->body, 'The empty translation should be used');
        $this->assertNull($result->description);
    }

    /**
     * Tests that allowEmptyTranslations takes effect
     */
    public function testEmptyTranslations(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', [
            'allowEmptyTranslations' => false,
        ]);
        $table->setLocale('zzz');
        $result = $table->get(1);

        $this->assertSame('First Article', $result->title, 'The empty translation should be ignored');
        $this->assertSame('First Article Body', $result->body, 'The empty translation should be ignored');
        $this->assertNull($result->description);
    }

    /**
     * Tests using FunctionExpression
     */
    public function testUsingFunctionExpression(): void
    {
        $this->skipIf(
            ConnectionManager::get('test')->getDriver() instanceof Postgres,
            'Test needs to be adjusted to not fail on Postgres',
        );

        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate');

        $table->setLocale('eng');
        $query = $table->find()->select();
        $query->select([
            'title',
            'function_expression' => $query->func()->concat(['ArticlesTranslation.title' => 'literal', ' with a suffix']),
            'body',
        ]);
        $result = array_intersect_key(
            $query->first()->toArray(),
            array_flip(['title', 'function_expression', 'body', '_locale']),
        );

        $expected = [
            'title' => 'Title #1',
            'function_expression' => 'Title #1 with a suffix',
            'body' => 'Content #1',
            '_locale' => 'eng',
        ];
        $this->assertSame(
            $expected,
            $result,
            'Including a function expression should work but requires referencing the used table aliases',
        );
    }

    /**
     * Ensure saving with accessible defined works
     *
     * With a standard baked model the accessible property is defined, that'll mean that
     * Setting fields such as id and locale will fail by default due to mass-assignment
     * protection. An exception is thrown if that happens
     */
    public function testSaveWithAccessibleFalse(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->setEntityClass(TranslateBakedArticle::class);
        $table->addBehavior('Translate', ['fields' => ['title', 'body']]);

        $article = $table->get(1);
        $article->translation('xyz')->title = 'XYZ title';

        $this->assertNotFalse($table->save($article), 'The save should succeed');
    }

    /**
     * Tests translationField method for translated fields.
     */
    public function testTranslationFieldForTranslatedFields(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', [
            'fields' => ['title', 'body'],
            'defaultLocale' => 'en_US',
        ]);

        $expectedSameLocale = 'Articles.title';
        $expectedOtherLocale = 'ArticlesTranslation.title';

        $field = $table->translationField('title');
        $this->assertSame($expectedSameLocale, $field);

        I18n::setLocale('es_ES');
        $field = $table->translationField('title');
        $this->assertSame($expectedOtherLocale, $field);

        I18n::setLocale('en');
        $field = $table->translationField('title');
        $this->assertSame($expectedOtherLocale, $field);

        $table->removeBehavior('Translate');
        $table->addBehavior('Translate', [
            'fields' => ['title', 'body'],
            'defaultLocale' => 'de_DE',
        ]);

        I18n::setLocale('de_DE');
        $field = $table->translationField('title');
        $this->assertSame($expectedSameLocale, $field);

        I18n::setLocale('en_US');
        $field = $table->translationField('title');
        $this->assertSame($expectedOtherLocale, $field);

        $table->setLocale('de_DE');
        $field = $table->translationField('title');
        $this->assertSame($expectedSameLocale, $field);

        $table->setLocale('es');
        $field = $table->translationField('title');
        $this->assertSame($expectedOtherLocale, $field);
    }

    /**
     * Test update entity with _translations field.
     *
     * Had to override this method because the core method has a wacky check
     * for "description" field which doesn't even exist in ArticleFixture.
     */
    public function testSaveExistingRecordWithTranslatesField(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', ['fields' => ['title', 'body']]);
        $table->setEntityClass(TranslateArticle::class);

        $data = [
            'author_id' => 1,
            'published' => 'Y',
            '_translations' => [
                'eng' => [
                    'title' => 'First Article1',
                    'body' => 'First Article content has been updated',
                ],
                'spa' => [
                    'title' => 'Mi nuevo titulo',
                    'body' => 'Contenido Actualizado',
                ],
            ],
        ];

        $article = $table->find()->first();
        $article = $table->patchEntity($article, $data);

        $this->assertNotFalse($table->save($article));

        $results = $this->_extractTranslations(
            $table->find('translations')->where(['id' => 1]),
        )->first();

        $this->assertSame('Mi nuevo titulo', $results['spa']['title']);
        $this->assertSame('Contenido Actualizado', $results['spa']['body']);

        $this->assertSame('First Article1', $results['eng']['title']);
    }

    /**
     * Test save new entity with _translations field
     */
    public function testSaveNewRecordWithTranslatesField(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->getValidator()->add('title', 'notBlank', ['rule' => 'notBlank']);
        $table->addBehavior('Translate', [
            'defaultLocale' => 'en',
            'fields' => ['title'],
        ]);
        $table->setEntityClass(TranslateArticle::class);

        $article = $table->patchEntity(
            $table->newEmptyEntity(),
            [
                '_translations' => ['en' => ['title' => '']],
            ],
        );
        $this->assertSame(
            ['notBlank' => 'The provided value is invalid'],
            $article->getError('title'),
        );

        $data = [
            'author_id' => 1,
            'published' => 'N',
            '_translations' => [
                'en' => [
                    'title' => 'Title EN',
                    'body' => 'Body EN',
                ],
                'es' => [
                    'title' => 'Title ES',
                ],
                'fr' => [
                    'title' => 'Title FR',
                ],
            ],
        ];

        $article = $table->patchEntity($table->newEmptyEntity(), $data);
        $result = $table->save($article);

        $this->assertNotFalse($result);

        $expected = [
            [
                'fr' => [
                    'title' => 'Title FR',
                    'locale' => 'fr',
                    'body' => null,
                ],
                'es' => [
                    'title' => 'Title ES',
                    'locale' => 'es',
                    'body' => null,
                ],
            ],
        ];
        $result = $table->find('translations')->where(['Articles.id' => $result->id])->all();
        $this->assertEquals($expected, $this->_extractTranslations($result)->toArray());

        $entity = $result->first();
        $this->assertSame('Title EN', $entity->title);
        $this->assertSame('Body EN', $entity->body);

        $data = [
            'title' => 'New title',
            'author_id' => 1,
            'published' => 'N',
            '_translations' => null,
        ];

        $article = $table->patchEntity($table->newEmptyEntity(), $data);
        $result = $table->save($article);

        $this->assertNotFalse($result);
    }

    /**
     * Tests adding new translation to a record
     */
    public function testInsertNewTranslations(): void
    {
        parent::testInsertNewTranslations();

        $shadowEntity = new class extends Entity {
            protected function _setComment($value)
            {
                return $value . ' modified';
            }
        };

        $table = $this->getTableLocator()->get('Comments');
        $table->addBehavior('Translate', ['fields' => ['comment']]);
        $table->setLocale('spa');
        $table->getStrategy()->getTranslationTable()->setEntityClass($shadowEntity::class);

        $entity = $table->get(1);
        $entity->comment = 'New Comment';
        $table->save($entity);

        $entity = $table->get(1);
        $this->assertSame(
            'New Comment',
            $entity->get('comment'),
            'New translation should not be modified',
        );
    }

    /**
     * Tests adding new translation to a record
     */
    public function testAllowEmptyFalse(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', ['fields' => ['title'], 'allowEmptyTranslations' => false]);

        $article = $table->find()->first();
        $this->assertSame(1, $article->get('id'));

        $article = $table->patchEntity($article, [
            '_translations' => [
                'fra' => [
                    'title' => '',
                ],
            ],
        ]);

        $table->save($article);

        $noFra = $table->ArticlesTranslations->find()->where(['locale' => 'fra'])->first();
        $this->assertEmpty($noFra);

        $article = $table->find()->where(['id' => 2])->first();

        $this->assertSame('Second Article', $article->get('title'));
        $table->patchEntity($article, ['title' => 'Second Article updated']);

        $this->assertNotFalse($table->save($article));
    }

    /**
     * Tests adding new translation to a record with a missing translation
     */
    public function testAllowEmptyFalseWithNull(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', ['fields' => ['title', 'description'], 'allowEmptyTranslations' => false]);

        $article = $table->find()->first();
        $this->assertSame(1, $article->get('id'));

        $article = $table->patchEntity($article, [
            '_translations' => [
                'fra' => [
                    'title' => 'Title',
                ],
            ],
        ]);

        $table->save($article);

        // Remove the Behavior to unset the content != '' condition
        $table->removeBehavior('Translate');

        $fra = $table->ArticlesTranslations->find()->where(['locale' => 'fra'])->first();
        $this->assertNotEmpty($fra);
    }

    /**
     * Tests adding new translation to a record
     */
    public function testMixedAllowEmptyFalse(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', ['fields' => ['title', 'body'], 'allowEmptyTranslations' => false]);

        $article = $table->find()->first();
        $this->assertSame(1, $article->get('id'));

        $article = $table->patchEntity($article, [
            '_translations' => [
                'fra' => [
                    'title' => '',
                    'body' => 'Bonjour',
                ],
            ],
        ]);

        $table->save($article);

        $fra = $table->ArticlesTranslations->find()
            ->where([
                'locale' => 'fra',
            ])
            ->first();
        $this->assertSame('Bonjour', $fra->body);
        $this->assertNull($fra->title);
    }

    /**
     * Tests adding new translation to a record
     */
    public function testMultipleAllowEmptyFalse(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', ['fields' => ['title', 'body'], 'allowEmptyTranslations' => false]);

        $article = $table->find()->first();
        $this->assertSame(1, $article->get('id'));

        $article = $table->patchEntity($article, [
            '_translations' => [
                'fra' => [
                    'title' => '',
                    'body' => 'Bonjour',
                ],
                'de' => [
                    'title' => 'Titel',
                    'body' => 'Hallo',
                ],
            ],
        ]);

        $table->save($article);

        $fra = $table->ArticlesTranslations->find()
            ->where([
                'locale' => 'fra',
            ])
            ->first();
        $this->assertSame('Bonjour', $fra->body);
        $this->assertNull($fra->title);

        $de = $table->ArticlesTranslations->find()
            ->where([
                'locale' => 'de',
            ])
            ->first();
        $this->assertSame('Titel', $de->title);
        $this->assertSame('Hallo', $de->body);
    }

    /**
     * Test buildMarshalMap() builds new entities.
     */
    public function testBuildMarshalMapBuildEntities(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        // Unlike test case of core Translate behavior "fields" is not set to
        // test marshalling with lazily fetched fields list.
        $table->addBehavior('Translate');
        $translate = $table->behaviors()->get('Translate');

        $map = $translate->buildMarshalMap($table->marshaller(), [], []);
        $entity = $table->newEmptyEntity();
        $data = [
            'en' => [
                'title' => 'English Title',
                'body' => 'English Content',
            ],
            'es' => [
                'title' => 'Titulo Español',
                'body' => 'Contenido Español',
            ],
        ];
        $result = $map['_translations']($data, $entity);
        $this->assertEmpty($entity->getErrors(), 'No validation errors.');
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('en', $result);
        $this->assertArrayHasKey('es', $result);
        $this->assertSame('English Title', $result['en']->title);
        $this->assertSame('Titulo Español', $result['es']->title);
    }

    /**
     * Used in the config tests to verify that a simple find still works
     *
     * @param string $tableAlias
     */
    protected function _testFind($tableAlias = 'Articles'): void
    {
        $table = $this->getTableLocator()->get($tableAlias);
        $table->setLocale('eng');

        $query = $table->find()->select();
        $result = array_intersect_key(
            $query->first()->toArray(),
            array_flip(['title', 'body', '_locale']),
        );
        $expected = [
            'title' => 'Title #1',
            'body' => 'Content #1',
            '_locale' => 'eng',
        ];
        $this->assertSame(
            $expected,
            $result,
            "Title and body are translated values, but don't match",
        );
    }

    /**
     * Tests that modified entities aren't marked as clean after ShadowTableStrategy::rowMapper
     */
    public function testModifiedEntityNotCleanAfterTranslationMapping(): void
    {
        $table = $this->getTableLocator()->get('Articles');
        $table->addBehavior('Translate', ['fields' => ['title', 'body']]);
        $table->setLocale('fra');

        $articles = $table->find()->all();
        $articles->each(function ($article): void {
            $article->published = 'N';
        });

        $this->assertTrue($articles->first()->isDirty('published'));
    }
}
