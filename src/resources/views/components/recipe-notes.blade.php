@props(['recipe'])
@if ($recipe->notes)
    <section class="card p-4">
        <h2 class="mb-2 text-lg font-semibold">Notes</h2>
        <p class="whitespace-pre-line">{{ $recipe->notes }}</p>
    </section>
@endif
