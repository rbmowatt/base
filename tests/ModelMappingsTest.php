<?php

namespace RBMowatt\BaseTests;

use BadMethodCallException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\BaseService;
use RBMowatt\Base\Services\Exceptions\InvalidQueryParamException;
use RBMowatt\Base\Services\ServiceResultsCollection;

class Survey extends BaseModel
{
    protected $table = 'surveys';

    protected $fillable = ['title'];

    public $timestamps = false;
}

class SurveyQuestion extends BaseModel
{
    protected $table = 'survey_questions';

    protected $fillable = ['survey_id', 'prompt'];

    public $timestamps = false;

    public function scopeAsked($query, $value, $operator = '=')
    {
        return $query->where('prompt', 'like', '%' . $value . '%');
    }

    public function scopeSortByPromptLength($query, $key, $direction = 'asc')
    {
        return $query->orderByRaw('length(prompt) ' . $direction);
    }
}

class SurveyCategory extends BaseModel
{
    protected $table = 'survey_categories';

    protected $fillable = ['label'];

    public $timestamps = false;
}

/**
 * @method SurveyQuestion|null findQuestion(int $id, array $with = [], array $selects = [])
 * @method ServiceResultsCollection whereQuestion(array $wheres, array $with = [], array $sorts = [], array $selects = [], int $limit = 20, int $page = 1)
 * @method ServiceResultsCollection allQuestions(array $wheres = [], array $with = [], array $sorts = [], array $selects = [])
 * @method SurveyQuestion createQuestion(array $params, callable|string $callback = '')
 * @method SurveyQuestion updateQuestion(SurveyQuestion|int $entity, array $args, callable|string $callback = '')
 * @method bool removeQuestion(int $id)
 * @method ServiceResultsCollection allCategories(array $wheres = [], array $with = [], array $sorts = [], array $selects = [])
 */
class SurveyService extends BaseService
{
    protected $filterable = ['title'];

    protected $sortable = ['title'];

    protected $mappingFilterable = ['question' => ['survey_id', 'id']];

    protected $mappingSortable = ['question' => ['prompt']];

    protected $mappingScopes = ['question' => ['asked' => 'asked']];

    protected $mappingSortScopes = ['question' => ['prompt_length' => 'sortByPromptLength']];

    public function __construct(
        protected Survey $surveyModel,
        protected SurveyQuestion $questionModel,
        protected SurveyCategory $categoryModel
    ) {
        $this->setModel($surveyModel);
    }

    public function getMappings(): array
    {
        return [
            'question' => $this->questionModel,
            'category' => $this->categoryModel,
        ];
    }
}

class ModelMappingsTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('prompt');
        });
        Schema::create('survey_categories', function (Blueprint $table) {
            $table->id();
            $table->string('label');
        });

        Survey::insert([['title' => 'intake'], ['title' => 'exit']]);
        SurveyQuestion::insert([
            ['survey_id' => 1, 'prompt' => 'how did you hear about us'],
            ['survey_id' => 1, 'prompt' => 'anything else'],
            ['survey_id' => 2, 'prompt' => 'why are you leaving'],
        ]);
        SurveyCategory::insert([['label' => 'onboarding'], ['label' => 'churn']]);
    }

    private function service(): SurveyService
    {
        return new SurveyService(new Survey(), new SurveyQuestion(), new SurveyCategory());
    }

    public function testFindRunsAgainstTheMappedModel(): void
    {
        $question = $this->service()->findQuestion(3);

        $this->assertInstanceOf(SurveyQuestion::class, $question);
        $this->assertSame('why are you leaving', $question->prompt);
    }

    public function testAMappedCallLeavesThePrimaryModelInPlace(): void
    {
        $service = $this->service();

        $service->findQuestion(1);

        $this->assertInstanceOf(Survey::class, $service->getModel());
        $this->assertSame('intake', $service->find(1)->title);
    }

    public function testCreateUpdateAndRemoveRunAgainstTheMappedModel(): void
    {
        $service = $this->service();

        $created = $service->createQuestion(['survey_id' => 2, 'prompt' => 'rate us']);
        $this->assertInstanceOf(SurveyQuestion::class, $created);

        $updated = $service->updateQuestion($created->id, ['prompt' => 'rate us, honestly']);
        $this->assertSame('rate us, honestly', $updated->prompt);

        $this->assertSame(1, $service->removeQuestion($created->id));
        $this->assertNull($service->findQuestion($created->id));
        $this->assertSame(2, Survey::count());
    }

    public function testPluralNamesResolveToTheSingularMapping(): void
    {
        $service = $this->service();

        $this->assertSame(3, $service->allQuestions()->count());
        $this->assertSame(2, $service->allCategories()->count());
    }

    public function testWhereReturnsAPaginatedCollectionOfMappedRows(): void
    {
        $results = $this->service()->whereQuestions([]);

        $this->assertInstanceOf(ServiceResultsCollection::class, $results);
        $this->assertSame(3, $results->getMeta('total'));
    }

    public function testAnUnknownMappingThrows(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->service()->findAnswer(1);
    }

    public function testTheActionHasToOpenTheMethodName(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->service()->refindQuestion(1);
    }

    public function testTheMappingNameHasToStartWithACapital(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->service()->findquestion(1);
    }

    public function testAServiceWithNoMappingsStillThrowsOnUnknownMethods(): void
    {
        $service = new class extends BaseService {
        };

        $this->expectException(BadMethodCallException::class);

        $service->findAnything(1);
    }

    public function testAColumnMissingFromTheMappingAllowlistIsRejected(): void
    {
        $this->expectException(InvalidQueryParamException::class);

        $this->service()->whereQuestion([['prompt', '=', 'anything else']]);
    }

    public function testAMappingWithNoAllowlistIsDefaultDeny(): void
    {
        $this->expectException(InvalidQueryParamException::class);

        $this->service()->whereCategory([['label', '=', 'churn']]);
    }

    public function testAnAllowlistedMappingColumnFilters(): void
    {
        $results = $this->service()->whereQuestion([['survey_id', '=', 1]]);

        $this->assertSame(2, $results->getMeta('total'));
    }

    public function testAMappingScopeFilters(): void
    {
        $results = $this->service()->whereQuestion([['asked', '=', 'leaving']]);

        $this->assertSame(1, $results->getMeta('total'));
        $this->assertSame('why are you leaving', collect($results->items())->first()->prompt);
    }

    public function testAnAllowlistedMappingColumnSorts(): void
    {
        $prompts = collect($this->service()->allQuestions([], [], [['prompt', 'desc']])->items())->pluck('prompt')->all();

        $this->assertSame(['why are you leaving', 'how did you hear about us', 'anything else'], $prompts);
    }

    public function testAMappingSortScopeSorts(): void
    {
        $prompts = collect($this->service()->allQuestions([], [], [['prompt_length', 'asc']])->items())->pluck('prompt')->all();

        $this->assertSame(['anything else', 'why are you leaving', 'how did you hear about us'], $prompts);
    }

    public function testAMappingAllowlistDoesNotLeakBackToThePrimaryModel(): void
    {
        $service = $this->service();
        $this->assertSame(1, $service->whereQuestion([['id', '=', 1]])->getMeta('total'));

        $this->expectException(InvalidQueryParamException::class);

        $service->where([['id', '=', 1]]);
    }

    public function testThePrimaryAllowlistStillAppliesToThePrimaryModel(): void
    {
        $this->assertSame(1, $this->service()->where([['title', '=', 'exit']])->getMeta('total'));
    }

    public function testThePrimaryAllowlistDoesNotCarryOverToAMapping(): void
    {
        $this->expectException(InvalidQueryParamException::class);

        $this->service()->whereQuestion([['title', '=', 'intake']]);
    }
}
