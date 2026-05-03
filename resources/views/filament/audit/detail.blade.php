<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-3">
        <div>
            <p class="font-medium text-gray-500">Waktu</p>
            <p>{{ $activity->created_at->format('d M Y H:i:s') }}</p>
        </div>
        <div>
            <p class="font-medium text-gray-500">Aksi</p>
            <p>{{ match($activity->event) {
                'created' => 'Dibuat',
                'updated' => 'Diubah',
                'deleted' => 'Dihapus',
                default   => $activity->event ?? '-',
            } }}</p>
        </div>
        <div>
            <p class="font-medium text-gray-500">Objek</p>
            <p>{{ class_basename($activity->subject_type ?? '') }} #{{ $activity->subject_id }}</p>
        </div>
        <div>
            <p class="font-medium text-gray-500">Dilakukan Oleh</p>
            <p>{{ $activity->causer?->name ?? 'Sistem' }}</p>
        </div>
    </div>

    @php
        $props = $activity->properties?->toArray() ?? [];
        $old   = $props['old'] ?? [];
        $new   = $props['attributes'] ?? [];
    @endphp

    @if(!empty($old))
        <div>
            <p class="mb-2 font-medium text-gray-500">Detail Perubahan</p>
            <div class="overflow-x-auto rounded border dark:border-gray-700">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left">Field</th>
                            <th class="px-3 py-2 text-left text-red-600">Nilai Lama</th>
                            <th class="px-3 py-2 text-left text-green-600">Nilai Baru</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @foreach($old as $field => $val)
                            <tr>
                                <td class="px-3 py-1 font-mono font-medium">{{ $field }}</td>
                                <td class="px-3 py-1 text-red-600">{{ is_array($val) ? json_encode($val) : $val }}</td>
                                <td class="px-3 py-1 text-green-600">{{ isset($new[$field]) ? (is_array($new[$field]) ? json_encode($new[$field]) : $new[$field]) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif(!empty($new))
        <div>
            <p class="mb-2 font-medium text-gray-500">Data yang Dibuat</p>
            <div class="overflow-x-auto rounded border dark:border-gray-700">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left">Field</th>
                            <th class="px-3 py-2 text-left">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @foreach($new as $field => $val)
                            <tr>
                                <td class="px-3 py-1 font-mono font-medium">{{ $field }}</td>
                                <td class="px-3 py-1">{{ is_array($val) ? json_encode($val) : $val }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <p class="text-gray-400">Tidak ada detail properti.</p>
    @endif
</div>
