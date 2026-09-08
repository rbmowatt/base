<?php

namespace RBMowatt\BaseTests;

use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Exception as BaseException;
use RBMowatt\Base\Rest\Exceptions\MissingParameterException;
use RBMowatt\Base\Rest\Exceptions\ValidationException;
use RuntimeException;

class ExceptionTest extends TestCase
{
    public function testPreviousIsChained(): void
    {
        $cause = new RuntimeException('the real cause');

        $this->assertSame($cause, (new BaseException('wrapped', 0, $cause))->getPrevious());
        $this->assertSame($cause, (new MissingParameterException('id', 0, $cause))->getPrevious());
        $this->assertSame($cause, (new ValidationException(null, 0, $cause))->getPrevious());
    }

    public function testAnyThrowableCanBeChained(): void
    {
        $cause = new \TypeError('not even an Exception');

        $this->assertSame($cause, (new BaseException('wrapped', 0, $cause))->getPrevious());
    }

    public function testCodeStillDefaultsToZero(): void
    {
        $this->assertSame(0, (new BaseException('no code'))->getCode());
        $this->assertSame(ErrorCodes::NO_RESULTS_FOUND, (new BaseException('coded', ErrorCodes::NO_RESULTS_FOUND))->getCode());
    }

    /**
     * Deprecations raised while a class is compiled fire once, before any test can
     * install a handler, so the package is loaded in a subprocess to catch them.
     */
    public function testPackageClassesLoadWithoutDeprecations(): void
    {
        $root = dirname(__DIR__);
        $script = <<<'PHP'
set_error_handler(function ($number, $message, $file, $line) {
    fwrite(STDOUT, "$message ($file:$line)\n");
    return true;
});
require $argv[1] . '/vendor/autoload.php';
$skip = $argv[1] . '/src/RBMowatt/Base/Tests';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1] . '/src'));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php' || str_starts_with($file->getPathname(), $skip)) {
        continue;
    }
    $class = str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($argv[1] . '/src/')));
    class_exists($class) || interface_exists($class) || trait_exists($class);
}
PHP;

        $output = shell_exec(
            PHP_BINARY . ' -d error_reporting=E_ALL -r ' . escapeshellarg($script) . ' ' . escapeshellarg($root) . ' 2>&1'
        );

        $this->assertSame('', trim((string) $output));
    }
}
