<div class="space-y-4 text-sm">
    @if($items->isEmpty())
        <p class="text-gray-400">Belum ada item checklist untuk milestone ini.</p>
    @else
        <div class="overflow-x-auto rounded border dark:border-gray-700">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">Nama</th>
                        <th class="px-3 py-2 text-left">Tipe</th>
                        <th class="px-3 py-2 text-center">Vendor</th>
                        <th class="px-3 py-2 text-center">Admin</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-3 py-2">{{ $item->nama }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $item->tipe === 'kegiatan' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                    {{ $item->tipe === 'kegiatan' ? 'Kegiatan' : 'Deliverable' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($item->is_done_vendor)
                                    <span class="text-green-600" title="{{ $item->vendor_done_at?->format('d M Y H:i') }}">&#10003;</span>
                                @else
                                    <span class="text-gray-300">&#10005;</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($item->is_done_admin)
                                    <span class="text-green-600" title="{{ $item->admin_done_at?->format('d M Y H:i') }}">&#10003;</span>
                                @else
                                    <span class="text-gray-300">&#10005;</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex gap-4 text-xs text-gray-500">
            <span>Vendor: {{ $items->where('is_done_vendor', true)->count() }}/{{ $items->count() }}</span>
            <span>Admin: {{ $items->where('is_done_admin', true)->count() }}/{{ $items->count() }}</span>
        </div>
    @endif
</div>
