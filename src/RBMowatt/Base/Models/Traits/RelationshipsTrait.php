<?php namespace RBMowatt\Base\Models\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

trait RelationshipsTrait
{
    protected $modelRelationships = [];

    /**
     * Get the model relations list so we can validate when asked
     *
     * Relations are found by their declared return type, never by calling a method
     * to see what comes back.
     * Note: a relation method with no return type is not found, and ?with= will reject it as an unknown relation.
     */
    public function relationships() {
        $model = new static;
        foreach((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
        {
            if ($method->class != get_class($model) || !$this->declaresARelation($method)) {
                continue;
            }
            // Safe to call now that the type says it is a relation: hasMany() and
            // friends build a query, they do not run one.
            $return = $method->invoke($model);

            if ($return instanceof Relation) {
                $this->modelRelationships[$method->getName()] = [
                    'fk'=>$this->getFkProperty($return)->getValue($return),
                    'type' => (new ReflectionClass($return))->getShortName(),
                    'model' => (new ReflectionClass($return->getRelated()))->getName()
                ];
            }
        }
        return $this->modelRelationships;
    }

    public function getRelationshipModel($type)
    {
        $r = $this->relationships();
        return App::make($r[$type]['model']);
    }

    public function getFk($type)
    {
        $r = $this->relationships();
        return $r[$type]['fk'];
    }

    /**
     * Does this method's signature say it returns a Relation?
     *
     * Reading the return type does not execute the method, which is the goal. Handles `?HasMany` and union returns; a method with no declared
     * return type is not a relation as far as this is concerned.
     *
     * @param ReflectionMethod $method
     * @return bool
     */
    protected function declaresARelation(ReflectionMethod $method)
    {
        if (!empty($method->getParameters()))
        {
            return false;
        }

        $declared = $method->getReturnType();
        if (!$declared)
        {
            return false;
        }

        $types = ($declared instanceof ReflectionUnionType) ? $declared->getTypes() : [$declared];
        foreach ($types as $type)
        {
            if ($type instanceof ReflectionNamedType
                && !$type->isBuiltin()
                && is_a($type->getName(), Relation::class, true))
            {
                return true;
            }
        }
        return false;
    }

    protected function getFkProperty($return)
    {
        $prop = null;
        $keys = ['foreignKey', 'foreignPivotKey', 'firstKey'];

        $p = new ReflectionClass($return);
        foreach($keys as $key)
        {
            if($p->hasProperty($key))
            {
                $prop = $p->getProperty($key);
                $prop->setAccessible(true);
                break;
            }
        }
        return $prop;
    }
}
