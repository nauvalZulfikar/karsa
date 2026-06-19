<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PekerjaanResource;
use App\Models\Master\Bidang;
use App\Models\MilestoneChecklistItem;
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

    public function toggleVendor(int $itemId): void
    {
        $item = MilestoneChecklistItem::find($itemId);
        if (!$item) return;
        abort_unless(
            auth()->user()->hasRole('vendor')
            && $item->milestonePekerjaan->pekerjaan->perusahaan_id === auth()->user()->perusahaan_id,
            403
        );
        $item->update([
            'is_done_vendor' => !$item->is_done_vendor,
            'vendor_done_at' => !$item->is_done_vendor ? now() : null,
        ]);
    }

    public function toggleAdmin(int $itemId): void
    {
        $item = MilestoneChecklistItem::find($itemId);
        if (!$item) return;
        abort_unless(auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin']), 403);
        abort_unless($item->is_done_vendor, 403);
        $item->update([
            'is_done_admin' => !$item->is_done_admin,
            'admin_done_at' => !$item->is_done_admin ? now() : null,
            'admin_done_by' => !$item->is_done_admin ? auth()->id() : null,
        ]);
    }

    public function getViewData(): array
    {
        $query = Pekerjaan::with([
                'perusahaan', 'bidang', 'statusPekerjaan', 'jenisPekerjaan',
                'milestones.checklistItems',
                'personil.tenagaAhli', 'terminPembayaran', 'dokumen',
            ])
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
                // Detail tambahan untuk accordion "Info & Progres"
                'jenis_pekerjaan'=> $p->jenisPekerjaan?->nama,
                'lokasi'         => $p->lokasi,
                'tahun_anggaran' => $p->tahun_anggaran,
                'satuan_waktu'   => $p->satuan_waktu,
                'tanggal_spk'    => $p->tanggal_spk?->format('d M Y'),
                'tanggal_spmk'   => $p->tanggal_spmk?->format('d M Y'),
                'catatan'        => $p->catatan,
                'personil_list'  => $p->personil->map(fn ($pp) => [
                    'nama'    => $pp->tenagaAhli?->nama ?? '-',
                    'jabatan' => $pp->jabatan_kontrak ?: '-',
                ])->toArray(),
                'termin_list'    => $p->terminPembayaran->map(fn ($t) => [
                    'nomor'  => $t->nomor_termin,
                    'nama'   => $t->nama_termin ?: ('Termin ' . $t->nomor_termin),
                    'persen' => $t->persen_progres_syarat !== null ? rtrim(rtrim(number_format((float) $t->persen_progres_syarat, 2, ',', '.'), '0'), ',') . '%' : '-',
                    'nilai'  => 'Rp ' . number_format((float) $t->nilai_termin, 0, ',', '.'),
                    'status' => $t->status_label,
                ])->toArray(),
                'dokumen_list'   => $p->dokumen->map(fn ($d) => [
                    'tipe'     => $d->tipe_label,
                    'nama'     => $d->nama_dokumen ?: ($d->file_original_name ?? '-'),
                    'versi'    => $d->versi ? ('v' . $d->versi) : '',
                ])->toArray(),
                'jumlah_personil'=> $p->personil->count(),
                'jumlah_termin'  => $p->terminPembayaran->count(),
                'jumlah_milestone'=> $p->milestones->count(),
                'status_label'   => $p->statusPekerjaan?->nama ?? ($p->status_waktu ?? 'Belum Mulai'),
                'url_detail'     => PekerjaanResource::getUrl('view', ['record' => $p->id]),
                'url_edit'       => PekerjaanResource::getUrl('edit', ['record' => $p->id]),
                'col_color'      => $columns[$status]['color'],
                'milestones'     => $p->milestones->map(fn ($m) => [
                    'id'       => $m->id,
                    'urutan'   => $m->urutan,
                    'nama'     => $m->nama,
                    'target'   => $m->tanggal_target?->format('d M Y'),
                    'progres'  => $m->progres_target_persen,
                    'status'   => $m->status_label,
                    'items'    => $m->checklistItems->map(fn ($ci) => [
                        'id'             => $ci->id,
                        'tipe'           => $ci->tipe,
                        'nama'           => $ci->nama,
                        'is_done_vendor' => (bool) $ci->is_done_vendor,
                        'is_done_admin'  => (bool) $ci->is_done_admin,
                    ])->toArray(),
                ])->toArray(),
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
