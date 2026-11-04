<?php

namespace App\Services\SmartFreezer;

use App\Models\FreezerControlCommand;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\SmartFreezerRecognition;
use App\Models\Vend;
use Illuminate\Support\Carbon;

/**
 * What the AI Recognition sidebar badge counts (Brian, 2026-11-04): every new thing an operator
 * should review. An AI verdict that is not a tick, an AI that could not answer, and a door the
 * machine refused or never got the command for, which leaves no verdict to look at.
 *
 * Same viewer ceiling as the page: an operator-restricted viewer counts its own machines only.
 */
final class RecognitionIssues
{
    private const DOOR_FAULT_OP = 'error:BoxVmc';

    /** The app's only lines for a door that failed; the same op also carries other host errors. */
    private const DOOR_FAULT_LINES = ['Door refused for %', 'Door call never reached host for %'];

    public function countSince(Carbon $since): int
    {
        return $this->verdictIssues($since) + $this->doorFaults($since);
    }

    /**
     * By `updated_at`, the moment the verdict was written: a TRADE arriving late judges a session
     * long after the AI's answer. No verdict yet is not an issue, since the sweep may still judge it.
     */
    private function verdictIssues(Carbon $since): int
    {
        return $this->forViewer(SmartFreezerRecognition::query())
            ->where('updated_at', '>', $since)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNotNull('verdict')->where('verdict', '<>', RecognitionVerdict::MATCH))
                ->orWhere('status', SmartFreezerRecognition::STATUS_FAILED))
            ->count();
    }

    private function doorFaults(Carbon $since): int
    {
        return $this->forViewer(FreezerControlCommand::query())
            ->where('op', self::DOOR_FAULT_OP)
            ->where('created_at', '>', $since)
            ->where(fn ($q) => $q
                ->where('response_msg', 'like', self::DOOR_FAULT_LINES[0])
                ->orWhere('response_msg', 'like', self::DOOR_FAULT_LINES[1]))
            ->count();
    }

    private function forViewer($query)
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();

        return $query->when($operatorId !== null, fn ($q) => $q->whereIn('vend_id', Vend::withoutGlobalScopes()->select('id')->where('operator_id', $operatorId)));
    }
}
