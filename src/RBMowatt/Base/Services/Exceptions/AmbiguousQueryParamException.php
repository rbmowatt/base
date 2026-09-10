<?php  namespace RBMowatt\Base\Services\Exceptions;

use RBMowatt\Base\Exception;

/**
* Raised when a filter key could mean two different things.
*
* isScope() maps a key to a scope by swapping underscores for dots, so the scope
* `widget.type.id` answers to `?widget_type_id=`. When a column of that exact name
* also exists and the Service has not listed it in $filterable, both readings are
* available and the old code silently took the scope — the caller asking to filter
* a column got a scope applied instead, with no indication.
*
* Listing the column in $filterable is the disambiguation: an explicit allowlist
* entry means the column wins. Renaming the scope key is the other way out.
*/
class AmbiguousQueryParamException extends Exception
{
    protected $scopeKey;

    public function __construct($key, $scopeKey, $message = null, $code = 0) {
        $this->scopeKey = $scopeKey;
        $message = $message ?: sprintf(
            'Query parameter [%s] matches both a column and the scope [%s]. '
            . 'List the column in $filterable to filter on it, or rename the scope.',
            $key,
            $scopeKey
        );
        parent::__construct($message, $code);
    }

    public function getScopeKey()
    {
        return $this->scopeKey;
    }
}
