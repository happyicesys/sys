<?php

namespace App\Console\Commands;

use App\Models\SmartFreezerOwnAiRecognition;
use App\Models\SmartFreezerRecognition;
use App\Services\SmartFreezer\OwnAi\OwnAiLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

/**
 * Our own freezer AI's answers from the office Macs ({@see OwnAiLink}). A Forge daemon of its own, on its own MQTT
 * connection and client id (`smart_freezer.own_ai.connection`), subscribed only to `<prefix>/results/+` and
 * `<prefix>/status/+` — never `#`, which the fleet listener (subscribe:mqtt) owns.
 *
 *   php artisan freezer-ai:listen            listen until stopped
 *   php artisan freezer-ai:listen --seconds=55   listen 55 s and stop: how prod runs it, from the every-minute scheduler
 *                                            (no daemon to create; the persistent session keeps what arrives in between)
 *   php artisan freezer-ai:listen --status   who is connected, and answers in the last 24 h
 *   php artisan freezer-ai:listen --send=187 announce a session now (a person's resend)
 */
class FreezerAiListen extends Command
{
    protected $signature = 'freezer-ai:listen {--status : list the Macs heard from and recent answers} {--send= : publish the job for this recognition id now} {--seconds= : stop after this many seconds}';

    protected $description = "Receive our own freezer AI's answers from the office Macs (MQTT)";

    public function handle(OwnAiLink $link): int
    {
        if ($this->option('status')) {
            return $this->status($link);
        }
        if ($id = $this->option('send')) {
            $recognition = SmartFreezerRecognition::with('vend')->find((int) $id);
            if ($recognition === null) {
                $this->error("No recognition {$id}");

                return self::FAILURE;
            }
            $link->publishJob($recognition, resend: true);   // a person's resend: sent again even if sent before
            $this->info("Sent session {$recognition->id} to {$link->prefix()}/jobs");

            return self::SUCCESS;
        }

        if (! config('smart_freezer.own_ai.enabled')) {
            return self::SUCCESS;                  // the link is off on this server (LSH, IDN): never connect
        }
        $prefix = $link->prefix();
        $mqtt = MQTT::connection((string) config('smart_freezer.own_ai.connection'));
        if ($seconds = (int) $this->option('seconds')) {
            $mqtt->registerLoopEventHandler(function ($client, float $elapsed) use ($seconds) {
                if ($elapsed >= $seconds) {
                    $client->interrupt();
                }
            });
        }
        // Every message goes through ONE received-message hook, registered before subscribing: on a persistent session
        // the broker hands over the answers queued while this listener was down right after CONNECT, before the
        // subscriptions are re-made, and the library drops a message no subscription callback matches (found on a
        // test broker, 2026-11-19).
        $mqtt->registerMessageReceivedEventHandler(function ($client, string $topic, string $message) use ($link, $mqtt) {
            try {
                if ($reply = $link->receive($topic, $message)) {
                    $mqtt->publish($reply[0], $reply[1], 1);
                }
            } catch (Throwable $e) {
                Log::error('freezer-ai message failed', ['topic' => $topic, 'exception' => get_class($e), 'message' => $e->getMessage()]);
            }
        });
        $mqtt->subscribe($prefix.'/results/+', null, 1);
        $mqtt->subscribe($prefix.'/status/+', null, 1);
        $this->info("Listening on {$prefix}/results/+ and {$prefix}/status/+");
        $mqtt->loop(true);
        if ($seconds) {
            $mqtt->disconnect();                   // clean: the broker keeps the session and queues for the next minute
        }

        return self::SUCCESS;
    }

    private function status(OwnAiLink $link): int
    {
        $workers = $link->workers();
        $since = now()->subDay();
        $this->table(['Mac', 'State', 'Role', 'Model', 'Last heard', 'Answers 24 h'], collect($workers)->map(fn ($s, $w) => [
            $w, $s['state'], $s['state'] === 'online' ? ($s['role'] ?? '') : '', $s['model'] ?? '', $s['seen_at'],
            SmartFreezerOwnAiRecognition::where('worker', $w)->where('received_at', '>=', $since)->count(),
        ])->values()->all());
        if ($workers === []) {
            $this->line('No Mac heard from yet.');
        }

        return self::SUCCESS;
    }
}
