<?php
namespace RBMowatt\Utilities\Rest\Interfaces;

interface ApiResponseInterface
{
    public function getStatusCode();

    public function ok($data, $code = 200);

    public function exception(\Exception $e, $code = 500);

    public function validationFails(\Validator $validator);
}
