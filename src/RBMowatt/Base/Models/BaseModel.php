<?php namespace RBMowatt\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use JsonSerializable;
use RBMowatt\Base\Models\Exceptions\ExtraneousDataException;
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
    return Cache::remember($this->table . '_tbl', 60 * 24, function () {
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

  public function softDelete()
  {
    $this->deleted_at = date(STANDARD_DATE_FORMAT);
    $this->save();
  }

}
