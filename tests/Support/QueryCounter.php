<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Counts the queries a piece of work actually runs.
 *
 * A dashboard that looks fast on a nearly empty database is not evidence of
 * anything. What matters is whether the query count grows when the data does, and
 * the only way to see that is to count.
 *
 * This is a measurement helper, not an assertion. Tests use it to prove a count
 * went down, and to fail loudly if a change quietly reintroduces a loop.
 */
final class QueryCounter
{
    /** @var list<array{sql: string, time: float, bindings: array<int, mixed>}> */
    private array $queries = [];

    private bool $recording = false;

    private bool $listening = false;

    /**
     * Attach the listener exactly once.
     *
     * A listener cannot be detached, so registering one per measurement leaves
     * every earlier listener attached and still recording. Each query is then
     * counted once per measurement ever taken: a report that really ran
     * twenty nine queries reports a hundred and forty five. The bug is
     * invisible in the code and completely wrong in the number, which is why
     * the counter lives here instead of inline in a test.
     */
    public function __construct()
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        DB::listen(function ($query): void {
            if (! $this->recording) {
                return;
            }

            $this->queries[] = [
                'sql' => $query->sql,
                'time' => (float) $query->time,
                'bindings' => $query->bindings,
            ];
        });
    }

    /**
     * Run $work and report how many queries it issued.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return array{result: T, count: int, time: float, queries: list<array{sql: string, time: float, bindings: array<int, mixed>}>}
     */
    public function measure(callable $work): array
    {
        $this->start();

        $started = microtime(true);
        $result = $work();
        $elapsed = (microtime(true) - $started) * 1000;

        $queries = $this->queries;
        $this->stop();

        return [
            'result' => $result,
            'count' => count($queries),
            'time' => $elapsed,
            'queries' => $queries,
        ];
    }

    public function start(): void
    {
        $this->queries = [];
        $this->recording = true;
    }

    public function stop(): void
    {
        $this->recording = false;
    }

    /**
     * Query shapes with the literals removed, most repeated first.
     *
     * Comparing shapes rather than raw SQL is what makes a duplicate visible:
     * fifty inserts that differ only by an id are one line here, not fifty.
     *
     * @param  list<array{sql: string, time: float, bindings: array<int, mixed>}>  $queries
     * @return array<string, int>
     */
    public static function shapes(array $queries): array
    {
        $shapes = [];

        foreach ($queries as $query) {
            $shape = trim(preg_replace('/\s+/', ' ', (string) preg_replace('/\d+/', '?', $query['sql'])) ?? '');
            $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
        }

        arsort($shapes);

        return $shapes;
    }

    /**
     * The most repeated shape, for a failure message.
     *
     * @param  list<array{sql: string, time: float, bindings: array<int, mixed>}>  $queries
     */
    public static function worst(array $queries): string
    {
        $shapes = self::shapes($queries);

        if ($shapes === []) {
            return 'no queries';
        }

        $shape = array_key_first($shapes);
        $count = $shapes[$shape];

        return "{$count}x {$shape}";
    }
}
