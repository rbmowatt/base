<?php  namespace RBMowatt\Base\Services\Exceptions;

use RBMowatt\Base\Exception;

/**
* Raised when a query key could mean two different things.
*
* $scopes and $sortScopes both map a key by swapping underscores for dots, so a
* scope declared `widget.type.id` answers to `?widget_type_id=`. When a column of
* that name also exists and the Service has not allowlisted it, both readings are
* live, and picking either one silently hands the caller a filter or an ordering
* they did not ask for.
*
* The allowlist is the disambiguation: an explicit $filterable entry (or $sortable,
* for a sort) means the column wins. Renaming the scope key is the other way out.
*/
class AmbiguousQueryParamException extends Exception
{
    protected $scopeKey;

    protected $allowlist;

    /**
    * @param string $key the request key that resolved two ways
    * @param string $scopeKey the declared scope key it matched
    * @param string $allowlist the Service property that settles it, without the $
    */
    public function __construct($key, $scopeKey, $allowlist = 'filterable', $message = null, $code = 0) {
        $this->scopeKey = $scopeKey;
        $this->allowlist = $allowlist;
        $message = $message ?: sprintf(
            'Query parameter [%s] matches both a column and the scope [%s]. '
            . 'List the column in $%s to use the column, or rename the scope.',
            $key,
            $scopeKey,
            $allowlist
        );
        parent::__construct($message, $code);
    }

    public function getAllowlist()
    {
        return $this->allowlist;
    }

    public function getScopeKey()
    {
        return $this->scopeKey;
    }
}
