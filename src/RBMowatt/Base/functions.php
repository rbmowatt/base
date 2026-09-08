<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

if (!defined('STANDARD_DATE_FORMAT')) {
    define('STANDARD_DATE_FORMAT', 'Y-m-d H:i:s');
}
/**
* var_dump  and exit alias
* @param  mixed $info 
* @return null
*/
if (!function_exists('vdd')) {
function vdd($info)
{
  var_dump($info);
  exit;
}
}

if (!function_exists('getVersion')) {
function getVersion()
{
  if(File::exists(getRootPath() . '/.app.info.php')) {
    require_once(getRootPath() . '/.app.info.php');
  }
  return (isset($appInfo['version'])) ? $appInfo['version'] : 'undefined';
}
}

if (!function_exists('getRootPath')) {
function getRootPath()
{
  return base_path();
}
}
/*
Will kill proccess and dump out whatever you pass
with additional info about file and line
*/
if (!function_exists('ldd')) {
function ldd()
{
  $bt = debug_backtrace();
  $caller = array_shift($bt);
  dd(['dd called @',$caller]);
}
}
/*
eval true based on string
*/
if (!function_exists('evalTruth')) {
function evalTruth($truthy)
{
  $truths = ['1', 'true'];
  return in_array($truthy, $truths);
}
}
/**
* Dumps query log from point it is called
* @param  boolean $dump should it kill and dump?
* @return nill
*/
if (!function_exists('qLog')) {
function qLog( $dump = false)
{
  Event::listen('Illuminate\Database\Events\QueryExecuted', function ($query) use ($dump) {
    foreach ($query->bindings as $i => $binding)
    {
      $bindings[$i] = ($binding instanceof \DateTime) ? $binding->format('\'Y-m-d H:i:s\'') : "'$binding'";
    }

    if(!isset($bindings))
    {
      ($dump) ? vdd($query->sql):Log::info($query->sql);
      return;
    }
    // Insert bindings into query
    $query = vsprintf(str_replace(array('%', '?'), array('%%', '%s'), $query->sql), $bindings);
    ($dump) ? vdd($query):Log::info($query);
  });


}
}
