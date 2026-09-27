<?php

namespace App\Services\SmartFreezer;

use App\Models\Vend;
use App\Services\SmartFreezer\Zijia\ZijiaVideoPush;

/**
 * Which freezer a Zijia push is about.
 *
 * In order: our own session ref (it names the vend code outright), then the modem IMEI, then
 * Zijia's device number. The last two are matched against what each freezer itself reported in
 * its status snapshot (`identity.imei` / `identity.deviceNo`, FREEZERCTLACK) — so there is no
 * second copy of the identity to keep in sync, and a board swap is picked up at its next boot.
 * Freezers number in the tens, so reading their snapshots is cheaper than an index to maintain.
 */
class FreezerDeviceResolver
{
    public function resolve(ZijiaVideoPush $push): ?Vend
    {
        if (($code = $push->sessionVendCode()) !== null) {
            // A bare number may also belong to an old vending machine (mark1 CLAUDE.md, machine ID
            // prefixes): only ever a freezer here.
            $vend = $this->freezers()->bareCode($code)->first();
            if ($vend) {
                return $vend;
            }
        }

        foreach ([$push->imei, $push->deviceNo] as $identifier) {
            if ($identifier !== null && ($vend = $this->byIdentity($identifier))) {
                return $vend;
            }
        }

        return null;
    }

    /** The freezer whose own snapshot names this IMEI or device number. */
    public function byIdentity(string $identifier): ?Vend
    {
        $needle = strtoupper(trim($identifier));
        if ($needle === '') {
            return null;
        }

        // A board moved to another cabinet leaves its IMEI in the old machine's last snapshot too:
        // an active machine, then the freshest snapshot, wins.
        return $this->freezers()
            ->whereNotNull('freezer_control_status_json')
            ->orderByDesc('is_active')
            ->orderByDesc('freezer_control_status_at')
            ->get()
            ->first(function (Vend $vend) use ($needle) {
                $identity = self::identityOf($vend);

                return in_array($needle, array_map(fn ($v) => strtoupper(trim((string) $v)), array_filter([
                    $identity['imei'] ?? null,
                    $identity['deviceNo'] ?? null,
                ])), true);
            });
    }

    private function freezers()
    {
        return Vend::withoutGlobalScopes()->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER);
    }

    /** @return array<string, mixed> the `identity` block of the freezer's last status snapshot */
    public static function identityOf(Vend $vend): array
    {
        $status = $vend->freezer_control_status_json;
        if (is_string($status)) {
            $status = json_decode($status, true);
        }

        return is_array($status) && is_array($status['identity'] ?? null) ? $status['identity'] : [];
    }
}
