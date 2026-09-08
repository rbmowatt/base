<?php namespace RBMowatt\Base;

use Exception as PhpException;
use Throwable;

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

    public function __construct($message, $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, (int) $code, $previous);
    }
}
