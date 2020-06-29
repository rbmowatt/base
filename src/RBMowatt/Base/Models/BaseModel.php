<?php namespace RBMowatt\Base\Models;

use DB;
use Exception;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use RBMowatt\Base\Models\Exceptions\ExtraneousDataException;
use RBMowatt\Base\Models\Interfaces\BaseModelInterface;
use RBMowatt\Base\Models\Traits\DateCalculationTrait;
use RBMowatt\Base\Models\Traits\PageAndLimitTrait;
use RBMowatt\Base\Models\Traits\PlusTheatreTrait;
use RBMowatt\Base\Models\Traits\RelationshipsTrait;
use RBMowatt\Utilities\Traits\ReflectionTrait;
use Schema;

class BaseModel extends Model implements BaseModelInterface,  JsonSerializable
{

  const NOT_EQUALS = "!=";
  const IN = "IN";

  use DateCalculationTrait;
  use PageAndLimitTrait;
  use ReflectionTrait;
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
  public function jsonSerialize() {
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
    if(!array_intersect(array_keys($args), $this->columns()) == $args)
    {
      throw new ExtraneousDataException('Invalid Arguments');
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
    return array_only($args, $this->columns());
  }

  public function softDelete()
  {
    $this->deleted_at = date(STANDARD_DATE_FORMAT);
    $this->save();
  }

}
