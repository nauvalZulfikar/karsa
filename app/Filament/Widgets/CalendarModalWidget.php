<?php

namespace App\Filament\Widgets;

use App\Models\Master\HariLibur;
use App\Models\MilestonePekerjaan;
use App\Models\Pekerjaan;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class CalendarModalWidget extends Widget
{
    protected static string $view = 'filament.widgets.calendar-modal';
    protected static ?int $sort = 99;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 'full';

    public bool $isOpen = false;
    public string $currentMonth;

    public function mount(): void
    {
        $this->currentMonth = Carbon::now()->format('Y-m');
    }

    #[On('open-calendar')]
    public function open(): void
    {
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function prevMonth(): void
    {
        $this->currentMonth = Carbon::createFromFormat('Y-m', $this->currentMonth)
            ->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->currentMonth = Carbon::createFromFormat('Y-m', $this->currentMonth)
            ->addMonth()->format('Y-m');
    }

    public function goToday(): void
    {
        $this->currentMonth = Carbon::now()->format('Y-m');
    }

    public function getViewData(): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $this->currentMonth . '-01');
        $end   = $start->copy()->endOfMonth();

        // Build calendar grid (start from Sunday before month start)
        $gridStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd   = $end->copy()->endOfWeek(Carbon::SATURDAY);

        $days = [];
        $cursor = $gridStart->copy();
        while ($cursor <= $gridEnd) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        // Fetch events for visible range
        $libur = HariLibur::whereBetween('tanggal', [$gridStart, $gridEnd])->get()
            ->groupBy(fn($l) => $l->tanggal->format('Y-m-d'));

        $milestones = MilestonePekerjaan::with('pekerjaan.bidang')
            ->whereBetween('tanggal_target', [$gridStart, $gridEnd])
            ->get()
            ->groupBy(fn($m) => $m->tanggal_target->format('Y-m-d'));

        $deadlines = Pekerjaan::with('bidang')
            ->whereBetween('tanggal_akhir', [$gridStart, $gridEnd])
            ->get()
            ->groupBy(fn($p) => $p->tanggal_akhir->format('Y-m-d'));

        // Bidang colors
        $bidangColors = [
            'Bangunan Gedung' => '#0ea5e9',
            'Jalan'           => '#10b981',
            'Drainase'        => '#8b5cf6',
            'Irigasi'         => '#f59e0b',
            'UMPEG'           => '#ec4899',
            'TARU'            => '#06b6d4',
            'JAKON'           => '#a855f7',
        ];

        // Merge events per day
        $eventsByDay = [];
        foreach ($days as $day) {
            $key = $day->format('Y-m-d');
            $events = [];
            foreach ($libur->get($key, []) as $l) {
                $events[] = [
                    'type'  => 'libur',
                    'label' => $l->nama,
                    'color' => '#ef4444',
                ];
            }
            foreach ($milestones->get($key, []) as $m) {
                $bidangNama = $m->pekerjaan?->bidang?->nama;
                $events[] = [
                    'type'  => 'milestone',
                    'label' => $m->nama . ' — ' . ($m->pekerjaan?->nama_pekerjaan ?? '-'),
                    'color' => $bidangColors[$bidangNama] ?? '#6b7280',
                ];
            }
            foreach ($deadlines->get($key, []) as $p) {
                $bidangNama = $p->bidang?->nama;
                $events[] = [
                    'type'  => 'deadline',
                    'label' => '⏰ DEADLINE: ' . $p->nama_pekerjaan,
                    'color' => '#dc2626',
                ];
            }
            $eventsByDay[$key] = $events;
        }

        return [
            'days'         => $days,
            'monthLabel'   => $start->locale('id')->translatedFormat('F Y'),
            'currentMonth' => $start->format('Y-m'),
            'startMonth'   => $start->format('m'),
            'eventsByDay'  => $eventsByDay,
            'today'        => Carbon::now()->format('Y-m-d'),
        ];
    }
}
