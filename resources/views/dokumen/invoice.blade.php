<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice — {{ $pekerjaan['nama'] }}</title>
    <style>
        @page { size: A4; margin: 1.5cm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10pt; color: #000; }
        h1 { font-size: 14pt; text-align: center; margin: 0 0 4px; }
        h2 { font-size: 11pt; text-align: center; margin: 0 0 12px; font-weight: normal; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 16px; }
        .meta-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .meta-table td { padding: 3px 6px; vertical-align: top; }
        .meta-table td:first-child { width: 30%; font-weight: bold; }
        table.line-items { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 9pt; }
        table.line-items th, table.line-items td { border: 1px solid #333; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.line-items th { background: #e8e8e8; text-align: center; }
        table.line-items td.num { text-align: right; }
        table.line-items td.ctr { text-align: center; }
        tr.section-header td { background: #f5f5f5; font-weight: bold; }
        tr.subtotal td { font-weight: bold; background: #fafafa; }
        tr.total td { font-weight: bold; background: #fff8dc; font-size: 10.5pt; }
        .signature { margin-top: 32px; width: 100%; }
        .signature td { width: 50%; vertical-align: top; padding: 0 10px; }
        .signature .label { font-weight: bold; }
        .signature .name { font-weight: bold; text-decoration: underline; padding-top: 60px; }
        .terbilang { font-style: italic; margin: 8px 0; }
    </style>
</head>
<body>

<div class="kop">
    <h1>{{ strtoupper($pekerjaan['vendor']) }}</h1>
    <div>{{ $pekerjaan['vendor_alamat'] ?? '' }}</div>
</div>

<h1>INVOICE</h1>
<h2>NO. INVOICE: INV/{{ $pekerjaan['no_invoice'] ?? '001' }}/{{ date('m') }}/{{ date('Y') }}</h2>

<table class="meta-table">
    <tr><td>Kepada</td><td>: Pejabat Pembuat Komitmen<br>{{ $pekerjaan['ppk_dinas'] ?? 'Dinas Pekerjaan Umum dan Tata Ruang Kabupaten Bandung' }}</td></tr>
    <tr><td>Pekerjaan</td><td>: {{ $pekerjaan['nama'] }}</td></tr>
    <tr><td>Lokasi</td><td>: {{ $pekerjaan['lokasi'] ?? '-' }}</td></tr>
    <tr><td>No. Kontrak</td><td>: {{ $pekerjaan['no_spk'] ?? '-' }}</td></tr>
    <tr><td>Tanggal Kontrak</td><td>: {{ $pekerjaan['tanggal_spk'] ?? '-' }}</td></tr>
    <tr><td>Periode</td><td>: {{ $pekerjaan['tanggal_mulai'] ?? '-' }} s/d {{ $pekerjaan['tanggal_akhir'] ?? '-' }}</td></tr>
    <tr><td>Prestasi</td><td>: {{ $prestasi_persen ?? 100 }}%</td></tr>
</table>

<table class="line-items">
    <thead>
        <tr>
            <th style="width:5%">No</th>
            <th>Uraian</th>
            <th style="width:8%">Volume</th>
            <th style="width:10%">Satuan</th>
            <th style="width:18%">Harga Satuan</th>
            <th style="width:20%">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @php
            $no = 0;
            $personil = collect($items)->where('kategori', 'personil');
            $nonPersonil = collect($items)->where('kategori', 'non_personil');
            $subtotalPersonil = $personil->sum('jumlah_harga');
            $subtotalNonPersonil = $nonPersonil->sum('jumlah_harga');
            $subtotal = $subtotalPersonil + $subtotalNonPersonil;
            $ppnPersen = $ppn_persen ?? 11;
            $ppn = $subtotal * $ppnPersen / 100;
            $totalDenganPpn = $subtotal + $ppn;
        @endphp

        @if($personil->count() > 0)
            <tr class="section-header"><td colspan="6">I. BIAYA LANGSUNG PERSONIL</td></tr>
            @foreach($personil as $item)
                @php $no++; @endphp
                <tr>
                    <td class="ctr">{{ $no }}</td>
                    <td>{{ $item['uraian'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item['volume'], 2, ',', '.'), '0'), ',') }}</td>
                    <td class="ctr">{{ $item['satuan'] }}</td>
                    <td class="num">Rp {{ number_format($item['harga_satuan'], 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format($item['jumlah_harga'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="5" class="num">Subtotal Personil</td>
                <td class="num">Rp {{ number_format($subtotalPersonil, 0, ',', '.') }}</td>
            </tr>
        @endif

        @if($nonPersonil->count() > 0)
            <tr class="section-header"><td colspan="6">II. BIAYA LANGSUNG NON PERSONIL</td></tr>
            @foreach($nonPersonil as $item)
                @php $no++; @endphp
                <tr>
                    <td class="ctr">{{ $no }}</td>
                    <td>{{ $item['uraian'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item['volume'], 2, ',', '.'), '0'), ',') }}</td>
                    <td class="ctr">{{ $item['satuan'] }}</td>
                    <td class="num">Rp {{ number_format($item['harga_satuan'], 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format($item['jumlah_harga'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="5" class="num">Subtotal Non Personil</td>
                <td class="num">Rp {{ number_format($subtotalNonPersonil, 0, ',', '.') }}</td>
            </tr>
        @endif

        <tr class="subtotal">
            <td colspan="5" class="num">JUMLAH</td>
            <td class="num">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
        </tr>
        <tr class="subtotal">
            <td colspan="5" class="num">PPN {{ $ppnPersen }}%</td>
            <td class="num">Rp {{ number_format($ppn, 0, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td colspan="5" class="num">TOTAL TERMASUK PPN</td>
            <td class="num">Rp {{ number_format($totalDenganPpn, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<div class="terbilang">
    <strong>Terbilang:</strong> {{ $terbilang ?? '(diisi otomatis)' }}
</div>

<table class="signature">
    <tr>
        <td class="ctr">
            <div class="label">Mengetahui/Menyetujui,<br>Pejabat Pembuat Komitmen</div>
            <div class="name">{{ $pekerjaan['ppk_nama'] ?? '(...)' }}</div>
            <div>{{ $pekerjaan['ppk_nip'] ?? 'NIP. -' }}</div>
        </td>
        <td class="ctr">
            <div class="label">Bandung, {{ $tanggal_terbit ?? date('d F Y') }}<br>{{ $pekerjaan['vendor'] }}</div>
            <div class="name">{{ $pekerjaan['vendor_direktur'] ?? 'Direktur' }}</div>
            <div>Direktur</div>
        </td>
    </tr>
</table>

</body>
</html>
