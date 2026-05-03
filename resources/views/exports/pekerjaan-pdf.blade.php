<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1f2937; }
    h2 { font-size: 13px; margin-bottom: 2px; }
    p.sub { font-size: 8px; color: #6b7280; margin: 0 0 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #dbeafe; font-size: 8px; padding: 4px 5px; text-align: left; border: 1px solid #bfdbfe; }
    td { padding: 3px 5px; border: 1px solid #e5e7eb; vertical-align: top; }
    tr:nth-child(even) td { background: #f9fafb; }
    .badge-aman     { background:#d1fae5; color:#065f46; padding:1px 5px; border-radius:3px; }
    .badge-waspada  { background:#fef3c7; color:#92400e; padding:1px 5px; border-radius:3px; }
    .badge-kritis   { background:#fee2e2; color:#991b1b; padding:1px 5px; border-radius:3px; }
    .badge-terlambat{ background:#fee2e2; color:#991b1b; padding:1px 5px; border-radius:3px; }
    .badge-selesai  { background:#d1fae5; color:#065f46; padding:1px 5px; border-radius:3px; }
    .footer { margin-top:16px; font-size:7px; color:#9ca3af; text-align:right; }
</style>
</head>
<body>
<h2>Rekap Pekerjaan — DPUTR Kabupaten Bandung</h2>
<p class="sub">
    Tahun Anggaran: {{ $tahun ?? 'Semua' }} |
    Bidang: {{ $bidangNama ?? 'Semua' }} |
    Dicetak: {{ now()->format('d M Y H:i') }}
</p>

<table>
    <thead>
        <tr>
            <th>No</th><th>Bidang</th><th>Nama Pekerjaan</th><th>Perusahaan</th>
            <th>Nilai Kontrak (Rp)</th><th>Progres</th><th>Deadline</th><th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $p)
        <tr>
            <td>{{ $i+1 }}</td>
            <td>{{ $p->bidang?->nama ?? '-' }}</td>
            <td>{{ $p->nama_pekerjaan }}</td>
            <td>{{ $p->perusahaan?->nama ?? '-' }}</td>
            <td style="text-align:right">{{ number_format($p->nilai_kontrak,0,',','.') }}</td>
            <td style="text-align:center">{{ $p->progres_persen ?? 0 }}%</td>
            <td>{{ $p->tanggal_akhir?->format('d/m/Y') ?? '-' }}</td>
            <td><span class="badge-{{ $p->status_waktu }}">{{ $p->status_waktu_label }}</span></td>
        </tr>
        @endforeach
    </tbody>
</table>

<p class="footer">DPUTR Project Management &mdash; Laporan otomatis sistem</p>
</body>
</html>
