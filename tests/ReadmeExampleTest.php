<?php
namespace RBMowatt\BaseTests;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\BaseService;

class Widg extends BaseModel
{
    protected $table = 'widgets';
    public $timestamps = false;

    public function scopeByWidgetType($query, $value, $operator = '=')
    {
        return $query->where('widgets.type_id', $operator, $value);
    }

    public function scopeSortByTypeName($query, $key, $direction)
    {
        return $query->orderBy(
            DB::table('widget_types')->select('name')->whereColumn('widget_types.id', 'widgets.type_id'),
            $direction
        );
    }
}

class WidgSvc extends BaseService
{
    protected $filterable = ['name', 'type_id'];
    protected $sortable = ['name', 'type_id'];
    protected $scopes = ['widget.type.id' => 'byWidgetType'];
    protected $sortScopes = ['type_name' => 'sortByTypeName'];
    public function __construct() { $this->primaryModel = new Widg(); }
}

/**
 * The three classes in the README's "What It Does" section, run as written. If this
 * fails the README is wrong.
 */
class ReadmeExampleTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default','testing');
        $app['config']->set('database.connections.testing',['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
    }
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('widget_types', function (Blueprint $t){ $t->id(); $t->string('name'); });
        Schema::create('widgets', function (Blueprint $t){ $t->id(); $t->string('name'); $t->integer('type_id'); });
        DB::table('widget_types')->insert([['name'=>'zebra'],['name'=>'aardvark']]);
        DB::table('widgets')->insert([
            ['name'=>'one','type_id'=>1],
            ['name'=>'two','type_id'=>2],
        ]);
    }
    public function testFilterScope(): void
    {
        $r = (new WidgSvc())->where([['widget_type_id','=',2]]);
        $this->assertSame(1, $r->count());
        $this->assertSame('two', $r->items()->first()->name);
    }
    public function testSortScope(): void
    {
        $r = (new WidgSvc())->where([], [], [['type_name','ASC']]);
        $this->assertSame(['two','one'], $r->items()->pluck('name')->all());
    }
}
