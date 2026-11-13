<?php

namespace App\Services\SmartFreezer\Claude;

/** An earlier door session shown to Claude with the count a person confirmed (ClaudeWorkedExamples). */
final class WorkedExample
{
    /**
     * @param  list<DoorFrame>  $frames
     * @param  array<int, int>  $answer  product id => units taken
     * @param  array<int, string>  $names  product id => name
     */
    public function __construct(
        public readonly int $recognitionId,
        public readonly string $label,
        public readonly array $frames,
        public readonly array $answer,
        public readonly array $names,
        public readonly string $source,
    ) {}

    /** "2 × Magnum Almond (product id 465)" lines, or "nothing was taken". */
    public function answerText(): string
    {
        if ($this->answer === []) {
            return 'nothing was taken';
        }
        $lines = [];
        foreach ($this->answer as $id => $units) {
            $lines[] = sprintf('%d × %s (product id %d)', $units, $this->names[$id] ?? 'product', $id);
        }

        return implode(', ', $lines);
    }
}
