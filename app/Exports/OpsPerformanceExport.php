<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;

/**
 * Excel export for Operations > Ops Performance.
 *
 * Renders the same data array the page receives via a Blade view, so the export
 * stays in sync with the page: KPI rows come from $kpis and the component
 * section from $component['groups'] — the same single source of truth. Adding a
 * KPI row or component category in the controller flows to both automatically.
 */
class OpsPerformanceExport implements FromView
{
    use Exportable;

    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.ops-performance', $this->data);
    }
}
