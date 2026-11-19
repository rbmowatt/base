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
  *
  * @param  array<int|string, static>  $models
  * @return BaseCollection<int|string, static>
  */
  public function newCollection(array $models = [])
  {
    return new BaseCollection($models);
  }
  public function jsonSerialize(): mixed {
    return $this->toArray();
  }

  public function isNotEqualsSIgn($sign)
  {
    return trim($sign) == self::NOT_EQUALS;
  }
  /**
  * @return array<int, string>
  */
  public function columns()
  {
    // Keyed off getTable(), not the $table property: that property is null on any
    // model that lets Eloquent derive its table name, so keying on it would make
    // every such model share one entry and serve each other's column lists. The TTL
    // argument is in seconds, so this is 24 hours.
    return Cache::remember('rbmowatt_base_columns_' . $this->getTable(), 60 * 60 * 24, function () {
      return Schema::getColumnListing($this->getTable());
    });
  }
  /**
  * @param  array<string, mixed>  $args
  * @return true
  * @throws ExtraneousDataException
  */
  public function validate( array $args)
  {
    // array_diff, not array_intersect compared against $args: the question is which
    // keys are NOT columns, and an intersect comparison lets a payload carrying one
    // real column plus any amount of junk pass.
    if($extraneous = array_diff(array_keys($args), $this->columns()))
    {
      throw new ExtraneousDataException('Invalid Arguments: ' . implode(', ', $extraneous));
    }
    return true;
  }
  /**
  * This will take an array and return an array with only keys that match columns
  *
  * @param  array<string, mixed>  $args
  * @return array<string, mixed>
  */
  public function filter(array $args)
  {
    return Arr::only($args, $this->columns());
  }

  /**
  * Soft delete through Eloquent rather than by hand.
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
