<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Quick "what's on this queue?" diagnostic — samples the Redis list backing
 * a Laravel queue and groups by job class so you can see at a glance what
 * makes up the backlog.
 *
 *   php artisan queue:inspect low                        # default 500 sample
 *   php artisan queue:inspect low --sample=2000          # bigger sample
 *   php artisan queue:inspect low --connection=redis     # if not default
 *
 * Output: a table of (job class | count in sample | % of sample) plus the
 * total queue depth and a few example payload IDs you can grep Horizon for.
 */
class InspectQueue extends Command
{
    protected $signature = 'queue:inspect
        {queue : Queue name (e.g. low / default / high)}
        {--sample=500 : How many entries to peek at (from the head of the list)}
        {--connection= : Redis connection name (default: queue.connections.redis.connection)}';

    protected $description = 'Sample a Redis queue and group entries by job class';

    public function handle(): int
    {
        $queue = $this->argument('queue');
        $sample = max(1, (int) $this->option('sample'));
        $conn = $this->option('connection') ?: config('queue.connections.redis.connection', 'default');

        $redisKey = 'queues:' . $queue;
        $redis = Redis::connection($conn);

        $total = (int) $redis->llen($redisKey);
        if ($total === 0) {
            $this->info(sprintf('Queue "%s" is empty.', $queue));
            return self::SUCCESS;
        }

        $this->info(sprintf('Queue "%s": total depth = %d, sampling first %d', $queue, $total, min($sample, $total)));

        $rawJobs = $redis->lrange($redisKey, 0, $sample - 1);

        $byClass = [];
        $exampleByClass = [];
        $delayed = 0;
        $reserved = 0;

        // Also count delayed + reserved (Horizon parks them in separate sorted sets)
        $delayed = (int) $redis->zcard($redisKey . ':delayed');
        $reserved = (int) $redis->zcard($redisKey . ':reserved');

        foreach ($rawJobs as $raw) {
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                $byClass['(unparseable)'] = ($byClass['(unparseable)'] ?? 0) + 1;
                continue;
            }

            $class = '(unknown)';
            if (isset($payload['displayName'])) {
                $class = $payload['displayName'];
            } elseif (isset($payload['data']['commandName'])) {
                $class = $payload['data']['commandName'];
            }

            $byClass[$class] = ($byClass[$class] ?? 0) + 1;
            if (!isset($exampleByClass[$class]) && isset($payload['id'])) {
                $exampleByClass[$class] = $payload['id'];
            }
        }

        arsort($byClass);
        $sampleSize = array_sum($byClass);

        $rows = [];
        foreach ($byClass as $class => $count) {
            $pct = $sampleSize ? round($count / $sampleSize * 100, 1) : 0;
            $rows[] = [
                'job_class' => $class,
                'in_sample' => $count,
                'pct' => $pct . '%',
                'projected_total' => round($count / max(1, $sampleSize) * $total),
                'example_id' => $exampleByClass[$class] ?? '',
            ];
        }

        $this->newLine();
        $this->table(
            ['Job class', 'In sample', '% of sample', 'Projected total', 'Example ID'],
            $rows
        );

        $this->newLine();
        $this->line(sprintf(' total in queue:    %d', $total));
        $this->line(sprintf(' delayed (waiting): %d', $delayed));
        $this->line(sprintf(' reserved (in flight): %d', $reserved));
        $this->newLine();
        $this->comment('Tip: search Horizon → Pending/Failed Jobs by an Example ID to see one job\'s full payload + tags.');

        return self::SUCCESS;
    }
}
