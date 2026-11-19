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
     * Cached, because discovery is not cheap and ?with= validation walks it once per
     * segment: reflecting the class is the small half, and building a relation object
     * for every relation method on it to read the related class is the big half.
     * Uncached that measures 0.36ms per ?with=a.b validation against 0.03ms cached.
     *
     * The map is class names, foreign keys and short type names, so it survives a
     * cache round trip intact. The key carries a fingerprint of the files that declare
     * the class, so a deploy that changes a relation reads a fresh key instead of the
     * previous release's map.
     *
     * Dropping the persistent cache for a per-process one does not pay: with opcache
     * warm, the first discovery of a seven-relation model in a request measured
     * 1.3-2.5ms, because every related model gets booted, against 0.06-0.09ms for a read
     * from the database store.
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
        return 'rbmowatt_base_relations_' . md5(static::class . '|' . $this->relationshipFingerprint());
    }

    /**
     * @var array<string, string>
     */
    protected static $relationshipFingerprints = [];

    /**
     * Path, mtime and size of every file that declares code on this class: the class
     * itself, each ancestor, and every trait any of them uses, nested traits included.
     *
     * Ancestors are in because, although a relation declared on a parent is not
     * discovered here, a parent can change getForeignKey() or $primaryKey and with
     * them the fk stored in the map. Related models' files are not: they only feed
     * the table qualifier on a HasMany fk, which ?with= validation never reads, and
     * finding them means running discovery, which is what the key exists to skip.
     *
     * Memoized per class for the life of the process because the code a process has
     * loaded cannot change under it; a deploy only reaches a fresh FPM request or a
     * restarted Octane worker, and both start with an empty map. Uncached, the
     * stats cost ~25-45us for a BaseModel subclass's 18 files, per call.
     *
     * @return string
     */
    protected function relationshipFingerprint()
    {
        if (isset(static::$relationshipFingerprints[static::class])) {
            return static::$relationshipFingerprints[static::class];
        }

        $parts = [];
        for ($class = new ReflectionClass(static::class); $class; $class = $class->getParentClass()) {
            $pending = [$class];
            while ($declaring = array_pop($pending)) {
                $file = $declaring->getFileName();
                // eval()'d code reports a pseudo path that is not on disk
                $parts[$declaring->getName()] = ($file !== false && is_file($file))
                    ? $file . ':' . filemtime($file) . ':' . filesize($file)
                    : '';
                foreach ($declaring->getTraits() as $trait) {
                    $pending[] = $trait;
                }
            }
        }
        ksort($parts);

        return static::$relationshipFingerprints[static::class] = md5(implode('|', $parts));
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
