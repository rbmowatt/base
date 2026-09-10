<?php namespace RBMowatt\Base\Models\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

trait RelationshipsTrait
{
    protected $modelRelationships = [];

    /**
    * How long the discovered relation map is kept. Seconds, matching columns().
    */
    const RELATIONSHIP_CACHE_TTL = 86400;

    /**
     * Get the model relations list so we can validate when asked
     *
     * Cached, because discovery is not cheap and ?with= validation walks it once
     * per segment: reflecting the class is the small half, and building a relation
     * object for every relation method on it to read the related class is the big
     * half. Measured at 0.36ms per ?with=a.b validation uncached against 0.03ms for
     * the root-only check this replaced.
     *
     * The map is class names, foreign keys and short type names, so it survives a
     * cache round trip intact. Add or rename a relation and the map is stale until
     * the TTL runs out — clear the cache on deploy, the same as columns().
     *
     * @return array<string, array<string, mixed>>
     */
    public function relationships() {
        return $this->modelRelationships = Cache::remember(
            $this->relationshipCacheKey(),
            self::RELATIONSHIP_CACHE_TTL,
            function () {
                return $this->discoverRelationships();
            }
        );
    }

    /**
    * Hashed because an anonymous class name is "class@anonymous" followed by a NUL
    * byte and the defining file path, which is not a legal cache key on every store.
    *
    * @return string
    */
    protected function relationshipCacheKey()
    {
        return 'rbmowatt_base_relations_' . md5(static::class);
    }

    /**
     * Walk the class for methods whose return type says they are a relation.
     *
     * Note: a relation method with no return type is not found, and ?with= will reject it as an unknown relation.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function discoverRelationships()
    {
        $found = [];
        $model = $this->newInstance();
        foreach((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
        {
            if ($method->class != get_class($model) || !$this->declaresARelation($method)) {
                continue;
            }
            // Safe to call now that the type says it is a relation: hasMany() and
            // friends build a query, they do not run one.
            $return = $method->invoke($model);

            if ($return instanceof Relation) {
                $found[$method->getName()] = [
                    'fk'=>$this->getFkProperty($return)->getValue($return),
                    'type' => (new ReflectionClass($return))->getShortName(),
                    'model' => (new ReflectionClass($return->getRelated()))->getName()
                ];
            }
        }
        return $found;
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
