<?php namespace RBMowatt\Base\Models;
use Illuminate\Database\Eloquent\Collection;
use JsonSerializable;
use Jsonable;
use Arrayable;


class BaseCollection extends Collection
{

    public function jsonSerialize() {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {

                return json_decode($value->toJson(), true);
            } elseif ($value instanceof Arrayable) {

                return $value->toArray();
            } else {
                return $value;
            }
        }, $this->items);
   }

   public function getValuesByKey($key, $values = [])
   {
       foreach($this->items as $i)
       {
           $values[] = $i->{$key};
       }
       return $values;
   }

   public function diffByKey($key = 'id', $values = [])
   {
       $ids = $this->whereIn($key,$values);
       return (count($ids) != count($values)) ? array_diff(array_values($values), array_values($ids->getValuesByKey($key))) : [];
   }

}
