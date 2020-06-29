<?php namespace RBMowatt\Utilities\Rest\Query;

use Auth;
use Cache;
use Exception;
use Illuminate\Http\Request;
use RBMowatt\Base\Rest\Exceptions\MissingParameterException;

class QueryParser
{
  /*
  A set of keys that are reserved and when encountered will always be used to tigger mapping function
  */
  protected $reservedKeys = ['select','where','with','limit', 'page', 'count', 'sort'];
  /*
  A list of accesptable sort oder descriptions
  */
  protected $validSortOrders = ['ASC', 'DESC', 'asc', 'desc'];

  const DEFAULT_LIMIT = 20;
  const DEFAULT_PAGE = 1;
  const SORT_DELIMITER = '_';
  const IN_CLAUSE = 'IN';

  public function __construct( Request $request )
  {
    $this->request = $request;
  }
  /**
   * Is the goal of this query just to get a count?
   * @return boolean
   */
  public function isCount()
  {
    return ($this->request->has('count') && $this->request->input('count') == 'true');
  }
  /**
  * route all generic property references to the request key
  * @param  string $key 
  * @return mixed
  */
  public function __get($key)
  {
    if(!$this->request->has($key))
    {
      throw new MissingParameterException($key);
    }
    return $this->request->input($key);
  }
  /**
  * Check to see if a parameter exists on the query
  * @param  string $key 
  * @return boolean
  */
  public function has($key)
  {
    return $this->request->has($key);
  }
  /**
  * get an array of sort patterns 
  * @param  array  $sorts 
  * @return array
  */
  public function getSorts($sorts = [])
  {
    if(!$this->request->has('sort')) return $sorts;
    $filters = is_array($f = $this->request->input('sort')) ? $f :[$f];
    foreach ($filters as $filter)
    {
      $p = explode(self::SORT_DELIMITER, $filter);
      if(!in_array($direction = trim(array_pop($p)), $this->validSortOrders))
      {
        throw new Exception('Invalid Sort Order ' . $direction);
      }
      $sorts[] = [implode(self::SORT_DELIMITER, $p), $direction];
    }
    return $sorts;
  }
  /**
  * Takes post data and turnns any comma delmited or special strings into values we can use
  * @param  they key to get the values from $requestKey [description]
  * @param  string $modelKey   [description]
  * @param  array  $filtered   [description]
  * @return [type]             [description]
  */
  public function filterPost($requestKey, $modelKey = 'id', $filtered = [])
  {
    $data = $this->{$requestKey};
    //there's only a single value being posted 
    if(!is_array($data)) return $this->convertToArray($data);
    //theres multiple values being sent
    foreach($data as $d)
    {
      if(is_array($d))
      {
        $filtered[] = $d[$modelKey];
        continue;
      }
      //else 
      $filtered[] = (is_object($d)) ? $d->{$modelKey} : $d;
    }
    return array_filter($filtered);
  }
  /**
  * get the limit on records to be returned
  * @return int
  */
  public function getLimit()
  {
    return ($this->request->input('limit')) ? $this->request->input('limit') : self::DEFAULT_LIMIT;
  }
  /**
  * get the offest on records to be returned
  * @return int
  */
  public function getPage()
  {
    return $this->request->input('page') ? $this->request->input('page') : self::DEFAULT_PAGE;
  }
  /**
  * get any specific select params passed
  * @return array
  */
  public function getSelects()
  {
    $filters = is_array($f = $this->request->input('select')) ? $f : $this->convertToArray($f);
    return $filters;
  }
  /**
  * [getWheres description]
  * @return [type] [description]
  */
  public function getWheres($customWheres=[], $without=[], $type = 'filter')
  {
    $filters = is_array($f = $this->request->input('where')) ? $f : $this->convertToArray($f);
    // this will gather any values placed in the array format ex : where=[manager.id!=139707,type_id=1,test=<123]
    $wheres = $this->makeWheres($filters);
    //the following handles the rest of the keys ex : ?key1=val1&key2=val2
      if ($type == 'filter') {
          $a = array_except($this->request->input(), array_merge($this->reservedKeys, $without));
          $ext = array_merge($customWheres, array_map(function ($k, $v) {
              return [$k, '=', $v];
          }, array_keys($a), $a));
      } else {
          $ext = $customWheres;
      }
    return  $this->replaceLessThan($this->replaceGtle(array_merge($wheres, $ext)));
  }
  /**
  * Turn wheres into a set of arrays following format [key, '= OR !=', value]
  * @param  array  $data 
  * @return array      
  */
  protected function makeWheres(array $data)
  {
    return array_filter(
      array_map(function($where){return preg_split('/([!]*[=])/', $where, -1, PREG_SPLIT_DELIM_CAPTURE);}, $data)
    );
  }
  /**
  * Will take the pk off a delete request and break it into an array
  * ex DELETE /sample/[1,2,3] becomes Array(1,2,3)
  * @param  [type] $key [description]
  * @return [type]      [description]
  */
  public function parseAttach($key)
  {
    return (count($data = $this->convertToArray($key)) ) ? $data : [$key];
  }
  /**
  * Will take the pk off a delete request and break it into an array
  * ex DELETE /sample/[1,2,3] becomes Array(1,2,3)
  * @param  string $key 
  * @return array
  */
  public function parseDetach($key)
  {
    return (count($data = $this->convertToArray($key)) ) ? $data : [$key];
  }
  /**
  * [convertToArray description]
  * @param  string  $data 
  * @return array
  */
  protected function convertToArray($data)
  {
    if(!$data) return [];
    //this handles caes like with=[a,b,c]
    if(preg_match('/\[.*?\]/', $data))
    {
      return array_map(function($d){ return trim($d);} ,explode(',', substr($data,1,-1)));
    }
    //this handles caes like with=a,b,c
    return (stristr($data, ",")) ? explode(',', $data) : [$data];
  }
  /**
  * Figure out what is greater than or equal to and create the proper format
  * looks for charachters APPENDED to `=`
  * @param  array $wheres 
  * @return array
  */
  protected function replaceGtle($wheres)
  {
    $fn = function($k)
    {
      $s = $k[2][0];
      if (in_array($s, ['<', '>']))
      {
        return [$k[0], $s, substr($k[2],1)];
      }
      return $k;
    };
    return array_map($fn , $wheres);
  }

  /**
  * Figure out what is less  and create the proper format
  * looks for charachters PREPENDED to `=`
  * @param  array $wheres 
  * @return array
  */
  protected function replaceLessThan($wheres)
  {
    $fn = function($k)
    {
      if(stristr($k[0], '!'))
      {
        $k[0] = substr($k[0], 0, -1);
        $k[1] = '!=';
      }
      return $k;
    };
    return array_map($fn , $wheres);
  }

}
