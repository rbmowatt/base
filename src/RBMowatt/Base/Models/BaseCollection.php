<?php namespace RBMowatt\Base\Models;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Collection;
use JsonSerializable;

/**
 * @template TKey of array-key
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends Collection<TKey, TModel>
 */
class BaseCollection extends Collection
{
    /**
     * Eloquent types a collection's items as Models, but this one is constructed
     * directly with plain Jsonable and Arrayable objects too, which is what the
     * branches in jsonSerialize() are for.
     *
     * @return array<TKey, mixed>
     */
    protected function rawItems(): array
    {
        return $this->items;
    }

    /**
     * @return array<TKey, mixed>
     */
    public function jsonSerialize(): array {
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
        }, $this->rawItems());
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
