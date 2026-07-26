<?php

namespace App\Http\Resources;

use App\Support\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // A session with no explicit end is only "active" while it is inside the
        // PHP session window; past that the cookie is dead and the user is gone,
        // we just never got told. Computed here rather than stamped by a cron so
        // the table can't drift out of date.
        $lifetime = (int) config('session.lifetime', 120);
        $lastSeen = $this->last_activity_at ?: $this->login_at;
        $stale = $lastSeen && $lastSeen->lt(now()->subMinutes($lifetime));

        if ($this->end_reason === 'logout') {
            $status = 'Logged out';
            $exact = true;
        } elseif ($this->end_reason === 'closed') {
            $status = 'Left / closed tab';
            $exact = true;
        } elseif ($stale) {
            $status = 'Expired (inferred)';
            $exact = false;
        } else {
            $status = 'Active';
            $exact = true;
        }

        $endedAt = $this->ended_at ?: ($stale ? $lastSeen : null);
        $seconds = ($this->login_at && $endedAt)
            ? max(0, $endedAt->getTimestamp() - $this->login_at->getTimestamp())
            : (($this->login_at && $lastSeen) ? max(0, $lastSeen->getTimestamp() - $this->login_at->getTimestamp()) : null);

        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'user_name'        => $this->user?->name,
            'user_email'       => $this->user?->email,
            'operator_code'    => $this->user?->operator?->code,
            'ip'               => $this->ip,
            'device_type'      => $this->device_type,
            'platform'         => $this->platform,
            'browser'          => $this->browser,
            'browser_version'  => $this->browser_version,
            'browser_label'    => trim(($this->browser ?: 'Unknown') . ' ' . ($this->browser_version ?: '')),
            'user_agent'       => $this->user_agent,
            'device_summary'   => UserAgentParser::summary($this->user_agent),
            'login_at'         => optional($this->login_at)->toDateTimeString(),
            'last_activity_at' => optional($this->last_activity_at)->toDateTimeString(),
            'ended_at'         => optional($endedAt)->toDateTimeString(),
            'end_reason'       => $this->end_reason,
            'status'           => $status,
            'status_exact'     => $exact,
            'duration_seconds' => $seconds,
            'duration_label'   => self::humanDuration($seconds),
            'page_view_count'  => (int) $this->page_view_count,
        ];
    }

    public static function humanDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
        }

        return intdiv($seconds, 3600) . 'h ' . intdiv($seconds % 3600, 60) . 'm';
    }
}
