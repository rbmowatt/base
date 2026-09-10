<?php

namespace Example\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RBMowatt\Base\Models\BaseModel;
use Example\Models\Interfaces\ExampleModelInterface;

class ExampleModel extends BaseModel implements ExampleModelInterface
{
    //just set the table name and you have a default connector to the example table
    protected $table = 'example';

    //Eloquent still guards mass assignment, and a Service create() goes through it
    protected $fillable = ['name', 'widget_type_id', 'account_type'];

    /**
    * Relations declared on the model are what ?with= is validated against.
    * Only methods declared on this class count, inherited ones are ignored.
    */
    public function widgetType(): BelongsTo
    {
        return $this->belongsTo(WidgetType::class, 'widget_type_id');
    }

    /**
    * A filter scope. ExampleService maps the incoming `widget.type.id` param to
    * this method, so the filter never has to exist as a column on this table.
    */
    public function scopeByWidgetType($query, $value, $operator = '=')
    {
        return $query->where('widget_type_id', $operator, $value);
    }

    /**
    * A sort scope, mapped from the `account_type` sort param by ExampleService.
    * Sort scopes are handed the key and the direction, in that order.
    */
    public function scopeSortByWidgetTypeName($query, $key, $direction)
    {
        return $query->orderBy(
            WidgetType::select('name')->whereColumn('widget_types.id', 'example.widget_type_id'),
            $direction
        );
    }
}
