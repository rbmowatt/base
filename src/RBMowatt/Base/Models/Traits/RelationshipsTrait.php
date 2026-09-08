<?php namespace RBMowatt\Base\Models\Traits;

use App;
use ErrorException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ReflectionClass;
use ReflectionMethod;

trait RelationshipsTrait
{
    protected $modelRelationships = [];

    /**
     * Get the models relations list so we can validate when asked
     */
    public function relationships() {
        $model = new static;
        foreach((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
        {
            if ($method->class != get_class($model) ||
            !empty($method->getParameters()) ||
            $method->getName() == __FUNCTION__) {
                continue;
            }
            try {
                $return = $method->invoke($model);

                if ($return instanceof Relation) {
                    $this->modelRelationships[$method->getName()] = [
                        'fk'=>$this->getFkProperty($return)->getValue($return),
                        'type' => (new ReflectionClass($return))->getShortName(),
                        'model' => (new ReflectionClass($return->getRelated()))->getName()
                    ];
                }
            } catch( Exception $e) {
                throw $e;
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
