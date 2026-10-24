Assalamualaikum dan salam sejahtera, {{ $penerima->name }}.

Ringkasan notifikasi anda ({{ $senarai->count() }}):

@foreach ($senarai as $item)
- {{ $item->subjek }} ({{ $item->created_at->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') }})
  {{ $item->pautanPenuh() }}
@endforeach

Buka peti notifikasi: {{ $pautanPeti }}

--
Ini adalah notifikasi automatik daripada SISDATA. Jangan balas e-mel ini.
