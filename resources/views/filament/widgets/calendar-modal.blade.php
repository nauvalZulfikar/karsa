<x-filament-widgets::widget>
    <style>
        .cal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; padding: 16px;
        }
        .cal-modal {
            background: #fff; border-radius: 20px;
            max-width: 1100px; width: 100%;
            max-height: 90vh; overflow: hidden;
            display: flex; flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .dark .cal-modal { background: #1f2937; color: #f3f4f6; }
        .cal-header {
            padding: 16px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
        }
        .dark .cal-header { border-color: #374151; }
        .cal-title {
            font-size: 18px; font-weight: 700; color: #1f2937;
            display: flex; align-items: center; gap: 12px;
        }
        .dark .cal-title { color: #f3f4f6; }
        .cal-nav { display: flex; gap: 6px; align-items: center; }
        .cal-nav-btn {
            background: #f3f4f6; border: none;
            width: 32px; height: 32px; border-radius: 50%;
            cursor: pointer; font-size: 14px; color: #374151;
            display: flex; align-items: center; justify-content: center;
        }
        .cal-nav-btn:hover { background: #e5e7eb; }
        .dark .cal-nav-btn { background: #374151; color: #e5e7eb; }
        .cal-nav-today {
            background: #f59e0b; color: #fff;
            padding: 6px 14px; border-radius: 9999px;
            font-size: 12px; font-weight: 600;
            border: none; cursor: pointer;
        }
        .cal-nav-today:hover { background: #d97706; }
        .cal-close {
            background: #f3f4f6; border: none;
            width: 32px; height: 32px; border-radius: 50%;
            cursor: pointer; font-size: 16px; color: #6b7280;
        }
        .cal-close:hover { background: #e5e7eb; }
        .dark .cal-close { background: #374151; color: #d1d5db; }

        .cal-grid-container { padding: 16px 24px; flex: 1; overflow-y: auto; }
        .cal-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-bottom: 4px;
        }
        .cal-weekday {
            font-size: 11px; font-weight: 700;
            color: #6b7280; text-transform: uppercase;
            padding: 6px 0; text-align: center;
            letter-spacing: 0.05em;
        }
        .dark .cal-weekday { color: #9ca3af; }
        .cal-weekday-sun { color: #ef4444; }
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-auto-rows: minmax(90px, auto);
            gap: 2px;
            background: #f3f4f6;
            border-radius: 10px;
            padding: 2px;
        }
        .dark .cal-grid { background: #374151; }
        .cal-day {
            background: #fff; border-radius: 8px;
            padding: 6px 8px;
            display: flex; flex-direction: column;
            min-height: 90px;
            position: relative;
            font-size: 12px;
        }
        .dark .cal-day { background: #1f2937; }
        .cal-day-other-month { background: #fafafa; opacity: 0.5; }
        .dark .cal-day-other-month { background: #111827; }
        .cal-day-today {
            background: #fef3c7;
            box-shadow: inset 0 0 0 2px #f59e0b;
        }
        .dark .cal-day-today { background: rgba(245,158,11,0.15); }
        .cal-day-num {
            font-size: 13px; font-weight: 600;
            color: #374151; margin-bottom: 4px;
        }
        .dark .cal-day-num { color: #e5e7eb; }
        .cal-day-num-sun { color: #ef4444; }
        .cal-day-num-libur {
            background: #ef4444; color: #fff;
            border-radius: 50%;
            width: 22px; height: 22px;
            display: inline-flex;
            align-items: center; justify-content: center;
            font-size: 11px;
        }
        .cal-events { display: flex; flex-direction: column; gap: 2px; overflow: hidden; }
        .cal-event {
            font-size: 10px; padding: 1px 6px;
            border-radius: 4px;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.3;
            cursor: default;
        }
        .cal-event:hover { opacity: 0.85; }
        .cal-more {
            font-size: 10px;
            color: #6b7280;
            font-weight: 600;
            cursor: pointer;
        }
        .dark .cal-more { color: #9ca3af; }

        .cal-legend {
            padding: 12px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex; gap: 16px; flex-wrap: wrap;
            font-size: 11px; color: #6b7280;
        }
        .dark .cal-legend { border-color: #374151; color: #9ca3af; }
        .cal-legend-item { display: flex; align-items: center; gap: 4px; }
        .cal-legend-dot {
            width: 10px; height: 10px; border-radius: 50%;
        }
    </style>

    @if ($isOpen)
        <div class="cal-overlay"
             wire:click.self="close"
             wire:keydown.escape.window="close">

            <div class="cal-modal" wire:click.stop>
                <div class="cal-header">
                    <div class="cal-title">
                        🗓️ {{ $monthLabel }}
                    </div>
                    <div class="cal-nav">
                        <button type="button" class="cal-nav-btn" wire:click="prevMonth" title="Bulan sebelumnya">‹</button>
                        <button type="button" class="cal-nav-today" wire:click="goToday">Hari Ini</button>
                        <button type="button" class="cal-nav-btn" wire:click="nextMonth" title="Bulan berikutnya">›</button>
                        <button type="button" class="cal-close" wire:click="close" title="Tutup" style="margin-left: 12px;">×</button>
                    </div>
                </div>

                <div class="cal-grid-container">
                    <div class="cal-weekdays">
                        @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $i => $w)
                            <div class="cal-weekday {{ $i === 0 ? 'cal-weekday-sun' : '' }}">{{ $w }}</div>
                        @endforeach
                    </div>
                    <div class="cal-grid">
                        @foreach ($days as $day)
                            @php
                                $dateKey = $day->format('Y-m-d');
                                $isOtherMonth = $day->format('m') !== $startMonth;
                                $isToday = $dateKey === $today;
                                $isSunday = $day->dayOfWeek === 0;
                                $events = $eventsByDay[$dateKey] ?? [];
                                $isLibur = collect($events)->contains(fn ($e) => $e['type'] === 'libur');
                            @endphp
                            <div class="cal-day {{ $isOtherMonth ? 'cal-day-other-month' : '' }} {{ $isToday ? 'cal-day-today' : '' }}"
                                 title="{{ $day->locale('id')->translatedFormat('l, d F Y') }}">
                                @if ($isLibur && !$isOtherMonth)
                                    <span class="cal-day-num-libur">{{ $day->day }}</span>
                                @else
                                    <div class="cal-day-num {{ ($isSunday && !$isOtherMonth) ? 'cal-day-num-sun' : '' }}">
                                        {{ $day->day }}
                                    </div>
                                @endif

                                <div class="cal-events">
                                    @foreach (array_slice($events, 0, 3) as $ev)
                                        <div class="cal-event"
                                             style="background: {{ $ev['color'] }};"
                                             title="{{ $ev['label'] }}">
                                            {{ \Illuminate\Support\Str::limit($ev['label'], 18) }}
                                        </div>
                                    @endforeach
                                    @if (count($events) > 3)
                                        <div class="cal-more">+{{ count($events) - 3 }} lagi</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="cal-legend">
                    <div class="cal-legend-item">
                        <span class="cal-legend-dot" style="background:#ef4444;"></span> Hari Libur
                    </div>
                    <div class="cal-legend-item">
                        <span class="cal-legend-dot" style="background:#0ea5e9;"></span> Milestone Bangunan
                    </div>
                    <div class="cal-legend-item">
                        <span class="cal-legend-dot" style="background:#10b981;"></span> Milestone Jalan
                    </div>
                    <div class="cal-legend-item">
                        <span class="cal-legend-dot" style="background:#8b5cf6;"></span> Milestone Drainase
                    </div>
                    <div class="cal-legend-item">
                        <span class="cal-legend-dot" style="background:#f59e0b;"></span> Milestone Irigasi
                    </div>
                    <div class="cal-legend-item">
                        <span class="cal-legend-dot" style="background:#dc2626;"></span> ⏰ Deadline Pekerjaan
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
