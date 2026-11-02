<?php

namespace App\Filament\Widgets;

use App\Models\Transfer;
use Filament\Widgets\ChartWidget;

/** Önümüzdeki 14 günün transfer yoğunluğu: filo planlaması için. */
class ReservationsChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Önümüzdeki 14 gün · transfer yoğunluğu';

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 1];

    protected function getData(): array
    {
        $days = collect(range(0, 13))->map(fn ($d) => today()->addDays($d));
        $rows = Transfer::query()
            ->whereBetween('pickup_at', [today(), today()->addDays(14)])
            ->where('status', '!=', 'cancelled')
            ->get(['pickup_at', 'direction']);

        $count = fn ($direction) => $days->map(fn ($day) => $rows
            ->filter(fn ($t) => $t->pickup_at->isSameDay($day) && $t->direction->value === $direction)->count())->all();

        return [
            'datasets' => [
                ['label' => 'Gidiş', 'data' => $count('outbound'), 'backgroundColor' => '#0ea5e9', 'borderRadius' => 4],
                ['label' => 'Dönüş', 'data' => $count('return'), 'backgroundColor' => '#94a3b8', 'borderRadius' => 4],
            ],
            'labels' => $days->map(fn ($d) => $d->translatedFormat('d M D'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'ticks' => ['precision' => 0]]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
