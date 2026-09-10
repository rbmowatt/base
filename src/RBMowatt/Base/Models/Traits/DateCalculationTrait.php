<?php namespace RBMowatt\Base\Models\Traits;

use RBMowatt\Base\Models\Exceptions\InvalidDateFormatException;

trait DateCalculationTrait
{
  const STANDARD_DATE_FORMAT = 'Y-m-d H:i:s';

  /**
  * Normalize a date query value into a `Y-m-d H:i:s` string.
  *
  * Accepts a unix timestamp, a `Y-m-d` or `Y-m-d H:i:s` string, or a relative
  * `3_days` (hour, day, week, month or year, singular or plural). Relative and
  * date-only values floor to midnight; only a full datetime keeps its time part.
  *
  * @param  string|int $date
  * @return string
  * @throws InvalidDateFormatException
  */
  protected function calculateSinceData($date)
  {
    //is it a unix timestamp?
    if( 1 == preg_match( '~^[1-9][0-9]*$~', $date ) )
    {
      return date(self::STANDARD_DATE_FORMAT,$date);
    }
    //is it some part of a DateTime?
    if(preg_match('/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/',$date)||preg_match('/(\d{4})-(\d{2})-(\d{2})/',$date))
    {
      $date = strtotime($date);
      return date(self::STANDARD_DATE_FORMAT, $date);
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
