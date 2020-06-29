<?php namespace RBMowatt\Base\Models\Traits;

use RBMowatt\Base\Models\Exception\InvalidDateFormatException;

trait DateCalculationTrait
{

  /**
  * [calculateSinceData description]
  * @param  [type] $date [description]
  * @return [type]       [description]
  */
  protected function calculateSinceData($date)
  {
    //is it a unix timestamp?
    if( 1 == preg_match( '~^[1-9][0-9]*$~', $date ) )
    {
      return date(STANDARD_DATE_FORMAT,$date);
    }
    //is it some part of a DateTime?
    if(preg_match('/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/',$date)||preg_match('/(\d{4})-(\d{2})-(\d{2})/',$date))
    {
      $date = strtotime($date);
      return date(STANDARD_DATE_FORMAT, $date);
    }
    //is it a strtotime?
    if(stristr($date,'_'))
    {
      $acceptableInput =  ['hour', 'hours','day', 'days','week', 'weeks','month','months', 'year','years'];
      $d = explode('_', $date);
      if(count($d)!=2 || !is_numeric($d[0]) || !in_array($d[1],$acceptableInput))
      {
        throw new InvalidDateFormatException('Invalid Date Query');
      }
      return date('Y-m-d 00:00:00', strtotime("-" . implode(" ", $d)));
    }
    throw new InvalidDateFormatException('Invalid Date Query');
  }
}
