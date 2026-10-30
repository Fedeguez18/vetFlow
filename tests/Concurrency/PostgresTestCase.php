<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Optional suite against a real PostgreSQL: SQLite ignores lockForUpdate(), so the protection of
 * checkout, stock, payments and voucher numbering against simultaneous operations is only proven here.
 *
 * It runs only when PGSQL_TEST_URL points to a disposable database whose name contains "test"
 * (every table is truncated between tests). A second connection plays the other terminal: it holds
 * a row lock inside an open transaction and the operation under test has to wait for it.
 *
 *   PGSQL_TEST_URL=postgresql://user:pass@host:5432/veteflow_test php artisan test --testsuite=Concurrency
 */
abstract class PostgresTestCase extends TestCase
{
    /**
     * SQLSTATE raised when lock_timeout expires while waiting for a row lock.
     */
    protected const LOCK_NOT_AVAILABLE = '55P03';

    protected static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $url = (string) env('PGSQL_TEST_URL', '');

        if ($url === '') {
            $this->markTestSkipped('Definí PGSQL_TEST_URL para correr la suite de concurrencia contra PostgreSQL.');
        }

        $database = ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        if (! str_contains(strtolower($database), 'test')) {
            $this->fail("PGSQL_TEST_URL apunta a la base \"{$database}\": tiene que ser una base descartable cuyo nombre contenga \"test\".");
        }

        $connection = ['url' => $url, 'driver' => 'pgsql'] + config('database.connections.pgsql');

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => $connection,
            'database.connections.pgsql_other_terminal' => $connection,
        ]);

        DB::purge('pgsql');
        DB::purge('pgsql_other_terminal');

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::connection('pgsql_other_terminal')->disconnect();
            DB::connection('pgsql')->disconnect();
        }

        parent::tearDown();
    }

    /**
     * Migrate once per run, then leave every table empty (with its ids restarted) before each test.
     */
    protected function resetDatabase(): void
    {
        if (! static::$migrated) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            static::$migrated = true;

            return;
        }

        $tables = collect(DB::select("select tablename from pg_tables where schemaname = 'public' and tablename <> 'migrations'"))
            ->map(fn (object $table) => '"'.$table->tablename.'"')
            ->implode(', ');

        DB::statement("truncate table {$tables} restart identity cascade");
    }

    /**
     * While the other terminal holds the lock taken by $lock, the operation has to wait for it
     * (it fails with lock_timeout instead of going ahead with data that is about to change).
     *
     * @param  callable(Connection): void  $lock
     */
    protected function assertWaitsForLock(callable $lock, callable $operation): void
    {
        $other = DB::connection('pgsql_other_terminal');
        $other->beginTransaction();

        try {
            $lock($other);
            DB::statement("set lock_timeout = '500ms'");

            try {
                $operation();
            } catch (QueryException $e) {
                $this->assertSame(self::LOCK_NOT_AVAILABLE, (string) $e->getCode(), $e->getMessage());

                return;
            }

            $this->fail('La operación no esperó el bloqueo que tenía la otra terminal.');
        } finally {
            $other->rollBack();
            DB::statement('set lock_timeout = 0');
        }
    }
}
