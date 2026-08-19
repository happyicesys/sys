<?php

namespace App\Services\Citybox\DTO;

/**
 * The exact array shape SyncVendChannels::handle() consumes — built by
 * ChannelFrameAdapter, and the ONE place in the CityBox code that knows it.
 * `label` B/A marks a restock before/after frame (vend_channel_records);
 * null = an ordinary stock update.
 */
final readonly class ChannelFrame
{
    /**
     * @param  array<int,array{channel_code:int,qty:int,capacity:int,amount:int,amount2:int,error_code:int}>  $channels
     */
    public function __construct(
        public array $channels,
        public ?string $label = null,
    ) {}

    public function toArray(): array
    {
        $arr = ['channels' => $this->channels];
        if ($this->label !== null) {
            $arr['label'] = $this->label;
        }

        return $arr;
    }

    public function isEmpty(): bool
    {
        return $this->channels === [];
    }
}
