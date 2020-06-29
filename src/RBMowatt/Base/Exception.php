<?php namespace RBMowatt\Base;

use Exception as PhpException;

class Exception extends PhpException
{

    const DEBUG = 'debug';
    const INFO = 'info';
    const NOTICE = 'notice';
    const WARNING = 'warning';
    const ERROR = 'error';
    const CRITICAL = 'critical';
    const ALERT = 'alert';
    const EMERGENCY = 'emergency';

    public function __construct($message, $code = null)
    {
        if($code)
        {
            parent::__construct($message, $code);
        }
        else
        {
            parent::__construct($message);
        }
    }
}
