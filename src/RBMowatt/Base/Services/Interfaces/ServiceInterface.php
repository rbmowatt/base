<?php namespace RBMowatt\Base\Services\Interfaces;
interface ServiceInterface
{
    public function find($id, array $with = [], $filters = []);
    public function where($wheres,  array $with = []);
    public function create($params, $callback = '');
    public function update($entity, $args, $callback = '');
    public function remove($id);
    public function getColumns();
    public function setModel($model);
    public function getScopes();
    public function setSorts($model, $sorts);
    public function confirmExistence($ids);
}
