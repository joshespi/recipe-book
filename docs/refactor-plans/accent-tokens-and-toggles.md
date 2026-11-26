# Refactor plan: accent tokens and segmented toggles

Found during the herb/stone reskin review (2026-11-26). Each section is independent.

## 1. Semantic accent color scale

**Why**: The red→herb reskin was done by hardcoding `herb-50` … `herb-950` utilities in ~20 Blade views. The theme already has `--color-accent` / `--color-accent-content`, but only two shades, so views bypass it. The next brand change means editing every view again.

**Where**:
- `src/resources/css/app.css` — `@theme` block (`--color-herb-*`, `--color-accent*`), `.dark` override in `@layer theme`, and components (`.field`, `.btn-primary`, `.masthead`, `.collection-heading`, `.collection-count`, headings)
- Views using `herb-*`: run `grep -rn "herb-" src/resources/views`. Main ones: `components/{category-chip,nav-link,recipe-card,recipe-details}.blade.php`, `components/layouts/{app,auth/simple}.blade.php`, `cook.blade.php`, `livewire/recipes/{cook-logs,form,index,photo-gallery,scaler,show}.blade.php`, `livewire/shopping-list.blade.php`, `shared/browse.blade.php`
- Hex duplicates of the brand color: `src/public/manifest.webmanifest` (`theme_color`), `src/resources/views/partials/head.blade.php` (`<meta name="theme-color">`)

**Proposed approach**:
- In `@theme`, add `--color-brand-50` … `--color-brand-950` as `var(--color-herb-*)` (name TBD: `brand` avoids clashing with Flux's `accent`).
- Mechanically replace `herb-` with `brand-` in views and CSS components.
- Keep `--color-herb-*` as the raw palette; only `brand-*` is referenced from markup.

**Risks / open questions**:
- Flux reads `--color-accent`, `--color-accent-content`, `--color-accent-foreground`; don't rename those. Decide whether `brand` and `accent` should be one scale or stay separate.
- Tailwind v4 only emits utilities for theme vars it sees used; verify the build output after the rename.
- The manifest/meta hex can't use CSS vars; leave them or generate from config.

**Suggested scope**: In: token definitions plus a mechanical rename in views/CSS. Out: changing any actual colors, and the remaining `red-*` used for danger/delete (keep those as red).

## 2. Segmented toggle component

**Why**: The same "button group with one active option" markup (active `bg-herb-600 text-white`, inactive `bg-white text-stone-700 hover:bg-stone-100 dark:…`, wrapping `inline-flex overflow-hidden rounded-lg border …` with `role="group"` and `aria-pressed`) is copy-pasted three times.

**Where**:
- `src/resources/views/livewire/recipes/scaler.blade.php` — scale presets group and units group
- `src/resources/views/livewire/shopping-list.blade.php` — units group
- Similar but different: tag pills in `livewire/recipes/index.blade.php`, cook-mode tabs in `cook.blade.php` (Alpine-driven), theme switcher in `components/layouts/app.blade.php`. Probably out of scope.

**Proposed approach**: Add `components/segmented.blade.php` taking `options` (value => label), `selected`, `label` (aria), and a `wire:click` template or an `action` prop, rendering the group and buttons. Replace the three Livewire call sites.

**Risks / open questions**:
- The scaler presets compare floats (`abs($factor - (float) $value) < 0.0005`), not plain equality. The component needs an `isActive` callback or a precomputed active key.
- Passing `wire:click` expressions through a component prop is awkward; consider slot-per-button instead of fully data-driven.

**Suggested scope**: In: the three Livewire segmented groups. Out: Alpine tabs, the theme switcher, tag pills.
