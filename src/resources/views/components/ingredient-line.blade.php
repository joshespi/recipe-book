{{-- One scaled ingredient (App\Support\ScaledLine): bold amount, unit, name, muted note. --}}
@props(['line'])
<span {{ $attributes }}>@if ($line->quantity)<strong class="font-semibold">{{ $line->quantity }}</strong>@endif @if ($line->unit){{ $line->unit }}@endif {{ $line->name }}@if ($line->note)<span class="text-stone-500 dark:text-stone-400">, {{ $line->note }}</span>@endif</span>
