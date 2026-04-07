<?php

namespace App\Livewire\Export;

use App\Livewire\Concerns\UsesApiClient;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Exports')]
class ExportPage extends Component
{
    use UsesApiClient;
    public string $exportType = 'orders';
    public string $format = 'csv';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function download()
    {
        // Route the export through the API layer which handles authorization and scoping
        $response = $this->api()->post('/exports', [
            'type' => $this->exportType,
            'format' => $this->format,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);

        if ($response->failed()) {
            session()->flash('flash_error', $response->json('message') ?? 'Export failed.');
            return null;
        }

        $ext = $this->format === 'pdf' ? 'pdf' : 'csv';
        $mime = $this->format === 'pdf' ? 'application/pdf' : 'text/csv';
        $filename = "{$this->exportType}_{$this->dateFrom}_{$this->dateTo}.{$ext}";

        return response()->streamDownload(function () use ($response) {
            echo $response->body();
        }, $filename, ['Content-Type' => $mime]);
    }

    public function render()
    {
        return view('livewire.export.export-page');
    }
}
