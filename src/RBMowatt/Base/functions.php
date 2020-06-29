<?php

const STANDARD_DATE_FORMAT = 'Y-m-d H:i:s';
/**
* var_dump  and exit alias
* @param  mixed $info 
* @return null
*/
function vdd($info)
{
  var_dump($info);
  exit;
}

function getVersion()
{
  if(File::exists(getRootPath() . '/.app.info.php')) {
    require_once(getRootPath() . '/.app.info.php');
  }
  return (isset($appInfo['version'])) ? $appInfo['version'] : 'undefined';
}

function getRootPath()
{
  return base_path();
}
/*
Will kill proccess and dump out whatever you pass
with additional info about file and line
*/
function ldd()
{
  $bt = debug_backtrace();
  $caller = array_shift($bt);
  dd(['dd called @',$caller]);
}
/*
eval true based on string
*/
function evalTruth($truthy)
{
  $truths = ['1', 'true'];
  return in_array($truthy, $truths);
}
/**
* Dumps query log from point it is called
* @param  boolean $dump should it kill and dump?
* @return nill
*/
function qLog( $dump = false)
{
  Event::listen('Illuminate\Database\Events\QueryExecuted', function ($query) use ($dump) {
    foreach ($query->bindings as $i => $binding)
    {
      $bindings[$i] = ($binding instanceof DateTime) ? $binding->format('\'Y-m-d H:i:s\'') : "'$binding'";
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
