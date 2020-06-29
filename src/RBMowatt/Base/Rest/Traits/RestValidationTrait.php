<?php

namespace RBMowatt\Utilities\Rest\Traits;

use RBMowatt\Utilities\Rest\Exceptions\ValidationException;
use Illuminate\Http\Request;
use Validator;

trait RestValidationTrait
{


    protected function justify(array $required, Request $request, $after = null)
    {
        $validator = Validator::make($request->all(), $this->parseValidation($required));
        if($after)
        {
          $validator->after($after);
        }
        if ($validator->fails())
        {
            throw new ValidationException($validator);
        }
    }

    private function parseValidation(array $required)
    {
        $parsedReqs = [];
        foreach($required as $key=>$value)
        {
            if($key === self::REQUIRED)
            {
                foreach($value as $v)
                {
                    (count($ex = explode('|', $v)) > 1) ? $parsedReqs[array_shift($ex)] = 'required|' . implode('|', $ex) : $parsedReqs[$v] = 'required';
                }
                continue;
            }
            $parsedReqs[$key] = $value;
        }
        return $parsedReqs;
    }

}
