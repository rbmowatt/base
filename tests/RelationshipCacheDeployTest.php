<?php

namespace RBMowatt\BaseTests;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Each read runs in its own PHP process against a shared file store, because a deploy
 * is a new process loading new code over a cache an old process filled; a class
 * cannot be redeclared inside one process to fake that.
 */
class RelationshipCacheDeployTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = sys_get_temp_dir() . '/rbmowatt-relation-deploy-' . bin2hex(random_bytes(6));
        mkdir($this->workDir . '/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->workDir);

        parent::tearDown();
    }

    public function testARelationAddedToTheModelResolvesAfterTheMapWasCached(): void
    {
        $model = $this->workDir . '/DeployRun.php';
        $this->writeRunModel($model, '');

        $before = $this->readInFreshProcess($model, 'session');
        $this->assertSame(['session'], $before['relations']);
        $this->assertTrue($before['resolves']);
        $this->assertFalse($this->readInFreshProcess($model, 'clip.session')['resolves']);

        $this->deploy($model, function () use ($model) {
            $this->writeRunModel($model, <<<'PHP'
                public function clip(): \Illuminate\Database\Eloquent\Relations\BelongsTo
                {
                    return $this->belongsTo(DeployClip::class, 'clip_id');
                }
            PHP);
        });

        $after = $this->readInFreshProcess($model, 'clip.session');
        $this->assertSame(['clip', 'session'], $this->sorted($after['relations']));
        $this->assertTrue($after['resolves']);
    }

    public function testARelationAddedThroughATraitResolvesAfterTheMapWasCached(): void
    {
        $trait = $this->workDir . '/HasDeployNotes.php';
        $model = $this->workDir . '/DeployRun.php';
        $this->writeNotesTrait($trait, '');
        $this->writeRunModel($model, 'use HasDeployNotes;', $trait);

        $this->assertSame(['session'], $this->readInFreshProcess($model, 'notes')['relations']);

        // only the trait's file changes; the model's file is byte-for-byte the same
        $this->deploy($trait, function () use ($trait) {
            $this->writeNotesTrait($trait, <<<'PHP'
                public function notes(): \Illuminate\Database\Eloquent\Relations\HasMany
                {
                    return $this->hasMany(DeployClip::class, 'run_id');
                }
            PHP);
        });

        $after = $this->readInFreshProcess($model, 'notes');
        $this->assertSame(['notes', 'session'], $this->sorted($after['relations']));
        $this->assertTrue($after['resolves']);
    }

    public function testAnUnchangedModelIsServedFromTheCacheAcrossProcesses(): void
    {
        $model = $this->workDir . '/DeployRun.php';
        $this->writeRunModel($model, '');

        $this->readInFreshProcess($model, 'session');
        $entries = $this->cacheEntries();
        $this->readInFreshProcess($model, 'session');

        $this->assertNotEmpty($entries);
        $this->assertSame($entries, $this->cacheEntries());
    }

    /**
     * Rewrites a file the way a release does, and pushes its mtime past the one the
     * cache was warmed against so a same-second rewrite cannot read as unchanged.
     */
    private function deploy(string $file, callable $rewrite): void
    {
        $was = filemtime($file);
        $rewrite();
        touch($file, $was + 10);
        clearstatcache();
    }

    private function writeRunModel(string $file, string $body, ?string $requires = null): void
    {
        $require = $requires ? 'require_once ' . var_export($requires, true) . ';' : '';
        file_put_contents($file, <<<PHP
            <?php
            namespace DeployFixture;
            {$require}

            class DeployClip extends \RBMowatt\Base\Models\BaseModel
            {
                protected \$table = 'deploy_clips';

                public function session(): \Illuminate\Database\Eloquent\Relations\BelongsTo
                {
                    return \$this->belongsTo(DeploySession::class, 'session_id');
                }
            }

            class DeploySession extends \RBMowatt\Base\Models\BaseModel
            {
                protected \$table = 'deploy_sessions';
            }

            class DeployRun extends \RBMowatt\Base\Models\BaseModel
            {
                protected \$table = 'deploy_runs';

                public function session(): \Illuminate\Database\Eloquent\Relations\BelongsTo
                {
                    return \$this->belongsTo(DeploySession::class, 'session_id');
                }

                {$body}
            }

            PHP);
    }

    private function writeNotesTrait(string $file, string $body): void
    {
        file_put_contents($file, <<<PHP
            <?php
            namespace DeployFixture;

            trait HasDeployNotes
            {
                {$body}
            }

            PHP);
    }

    /**
     * @return array{relations: list<string>, resolves: bool}
     */
    private function readInFreshProcess(string $modelFile, string $path): array
    {
        $process = new Process([
            PHP_BINARY,
            __DIR__ . '/Fixtures/relation-cache-process.php',
            $this->workDir . '/cache',
            $modelFile,
            'DeployFixture\\DeployRun',
            $path,
        ]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, int>
     */
    private function cacheEntries(): array
    {
        $entries = [];
        foreach ((new Filesystem())->allFiles($this->workDir . '/cache') as $file) {
            $entries[$file->getRelativePathname()] = $file->getMTime();
        }
        ksort($entries);

        return $entries;
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
