<?php namespace RBMowatt\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use JsonSerializable;
use RBMowatt\Base\Models\Exceptions\ExtraneousDataException;
use RBMowatt\Base\Models\Exceptions\SoftDeletesNotEnabledException;
use RBMowatt\Base\Models\Interfaces\BaseModelInterface;
use RBMowatt\Base\Models\Traits\DateCalculationTrait;
use RBMowatt\Base\Models\Traits\PageAndLimitTrait;
use RBMowatt\Base\Models\Traits\RelationshipsTrait;

class BaseModel extends Model implements BaseModelInterface,  JsonSerializable
{

  const NOT_EQUALS = "!=";
  const IN = "IN";

  use DateCalculationTrait;
  use PageAndLimitTrait;
  use RelationshipsTrait;


  protected $searchRules = [

  ];
  protected $collapse = [];

  /**
  * Overrides laravel default and sets base collection as collection object returned
  * @param  array  $models [description]
  * @return BaseCollection  [description]
  */
  public function newCollection(array $models = [])
  {
    return new BaseCollection($models);
  }
  /**
  * [jsonSerialize description]
  * @return [type] [description]
  */
  public function jsonSerialize(): mixed {
    return $this->toArray();
  }

  public function isNotEqualsSIgn($sign)
  {
    return trim($sign) == self::NOT_EQUALS;
  }
  /**
  * [columns description]
  * @return [type] [description]
  */
  public function columns()
  {
    // Keyed off getTable(), not the $table property: the property is null on any
    // model that lets Eloquent derive its table name, so every one of them shared
    // the key '_tbl' and served each other's column lists. The TTL is seconds
    // (Laravel 5.8 changed it from minutes), so 60 * 24 was 24 minutes, not a day.
    return Cache::remember('rbmowatt_base_columns_' . $this->getTable(), 60 * 60 * 24, function () {
      return Schema::getColumnListing($this->getTable());
    });
  }
  /**
  * [validate description]
  * @param  array  $args [description]
  * @return [type]       [description]
  */
  public function validate( array $args)
  {
    // This was `!array_intersect(...) == $args`, and `!` binds tighter than `==`,
    // so it compared a bool to the payload. A payload holding one real column plus
    // any number of junk keys came out valid.
    if($extraneous = array_diff(array_keys($args), $this->columns()))
    {
      throw new ExtraneousDataException('Invalid Arguments: ' . implode(', ', $extraneous));
    }
    return true;
  }
  /**
  * This will take an array and return an array with only keys that match columns
  * 
  * @param  array  $args [description]
  * @return [type]       [description]
  */
  public function filter(array $args)
  {
    return Arr::only($args, $this->columns());
  }

  /**
  * Soft delete through Eloquent rather than by hand.
  *
  * This used to set deleted_at and save(). On a model without the SoftDeletes
  * trait that wrote the column and changed nothing else, so the row kept coming
  * back from every query, and on a table with no deleted_at column it died with
  * "no such column: deleted_at". Neither told the caller anything was wrong.
  *
  * @return bool|null
  * @throws SoftDeletesNotEnabledException when the model has no SoftDeletes trait
  */
  public function softDelete()
  {
    if (!in_array(SoftDeletes::class, class_uses_recursive(static::class), true))
    {
      throw new SoftDeletesNotEnabledException(
        static::class . ' does not use ' . SoftDeletes::class
        . ', so it cannot be soft deleted. Add the trait and a deleted_at column, or call delete().'
      );
    }
    return $this->delete();
  }

}
