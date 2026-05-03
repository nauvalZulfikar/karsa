<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PekerjaanResource;
use App\Models\Master\Bidang;
use App\Models\Pekerjaan;
use Filament\Widgets\Widget;

class KanbanPekerjaanWidget extends Widget
{
    protected static string $view = 'filament.widgets.kanban-pekerjaan';
    protected static ?int $sort = 1;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 'full';

    public ?int $filterBidangId = null;

    public function setFilter(?int $bidangId): void
    {
        $this->filterBidangId = $bidangId;
    }

    public function getViewData(): array
    {
        $query = Pekerjaan::with(['perusahaan', 'bidang', 'statusPekerjaan'])
            ->where('tahun_anggaran', date('Y'));

        if ($this->filterBidangId) {
            $query->where('bidang_id', $this->filterBidangId);
        }

        $pekerjaans = $query->latest()->get();

        $columns = [
            'belum_mulai' => ['label' => '📋 Backlog',    'color' => '#6b7280', 'items' => []],
            'aman'        => ['label' => '🟢 Aman',       'color' => '#10b981', 'items' => []],
            'waspada'     => ['label' => '🟡 Waspada',    'color' => '#f59e0b', 'items' => []],
            'kritis'      => ['label' => '🔴 Kritis',     'color' => '#ef4444', 'items' => []],
            'terlambat'   => ['label' => '⛔ Terlambat',  'color' => '#dc2626', 'items' => []],
            'selesai'     => ['label' => '✅ Selesai',    'color' => '#0ea5e9', 'items' => []],
        ];

        foreach ($pekerjaans as $p) {
            $status = $p->status_waktu ?? 'belum_mulai';
            if (!isset($columns[$status])) continue;

            $columns[$status]['items'][] = [
                'id'             => $p->id,
                'nama'           => $p->nama_pekerjaan,
                'no_spk'         => $p->no_spk,
                'no_spmk'        => $p->no_spmk,
                'vendor'         => $p->perusahaan?->nama,
                'bidang'         => $p->bidang?->nama,
                'progres'        => (float) $p->progres_persen,
                'sisa_hari'      => $p->sisa_hari,
                'nilai_pagu'     => 'Rp ' . number_format((float) $p->nilai_pagu, 0, ',', '.'),
                'nilai_kontrak'  => 'Rp ' . number_format((float) $p->nilai_kontrak, 0, ',', '.'),
                'tanggal_mulai'  => $p->tanggal_mulai?->format('d M Y'),
                'tanggal_akhir'  => $p->tanggal_akhir?->format('d M Y'),
                'hari_kerja'     => $p->hari_kerja,
                'jumlah_personil'=> $p->personil()->count(),
                'jumlah_termin'  => $p->terminPembayaran()->count(),
                'jumlah_milestone'=> $p->milestones()->count(),
                'status_label'   => $p->statusPekerjaan?->nama,
                'url_detail'     => PekerjaanResource::getUrl('view', ['record' => $p->id]),
                'url_edit'       => PekerjaanResource::getUrl('edit', ['record' => $p->id]),
                'col_color'      => $columns[$status]['color'],
            ];
        }

        // Hanya tampilkan bidang yang punya pekerjaan di tahun ini
        $bidangIdsWithData = Pekerjaan::where('tahun_anggaran', date('Y'))
            ->distinct()
            ->pluck('bidang_id');

        $bidangList = Bidang::whereIn('id', $bidangIdsWithData)
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return [
            'columns'        => $columns,
            'bidangList'     => $bidangList,
            'filterBidangId' => $this->filterBidangId,
        ];
    }
}
