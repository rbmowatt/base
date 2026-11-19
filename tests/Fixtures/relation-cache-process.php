<?php

use Orchestra\Testbench\Foundation\Application;
use RBMowatt\Base\Services\BaseService;

[, $cacheDir, $modelFile, $modelClass, $path] = $argv;

require __DIR__ . '/../../vendor/autoload.php';

$app = Application::create();
$app['config']->set('cache.default', 'file');
$app['config']->set('cache.stores.file', ['driver' => 'file', 'path' => $cacheDir]);

require $modelFile;

$service = new class(new $modelClass()) extends BaseService {
    public function __construct($model)
    {
        $this->primaryModel = $model;
    }

    public function resolves($path)
    {
        return $this->relationPathResolves($this->primaryModel, $path);
    }
};

echo json_encode([
    'relations' => array_keys((new $modelClass())->relationships()),
    'resolves' => $service->resolves($path),
]);
