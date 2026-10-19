<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Jobs\ProcessPhoto;
use App\Support\UniqueSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Recipe extends Model
{
    /** @use HasFactory<\Database\Factories\RecipeFactory> */
    use HasFactory, SoftDeletes;

    public const PRIVATE = 'private';

    public const UNLISTED = 'unlisted';

    public const PUBLIC = 'public';

    public const VISIBILITIES = [self::PRIVATE, self::UNLISTED, self::PUBLIC];

    protected $fillable = [
        'title', 'description', 'servings', 'yield_label',
        'prep_minutes', 'cook_minutes', 'total_minutes',
        'source_name', 'source_url', 'notes',
        'visibility', 'is_favorite', 'rating',
    ];

    protected function casts(): array
    {
        return [
            'servings' => 'float',
            'is_favorite' => 'boolean',
            'rating' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Recipe $recipe) {
            $recipe->share_token ??= Str::random(32);
            $recipe->slug ??= UniqueSlug::make(static::withTrashed(), $recipe->title, 'recipe');
        });
    }

    public function regenerateShareToken(): void
    {
        $this->forceFill(['share_token' => Str::random(32)])->save();
    }

    public function isShareable(): bool
    {
        return $this->visibility !== self::PRIVATE;
    }

    /** The one definition of "reachable by share link": any recipe that is not private. */
    public static function findShared(string $token): self
    {
        return static::query()
            ->where('share_token', $token)
            ->where('visibility', '!=', self::PRIVATE)
            ->firstOrFail();
    }

    /** Makes a photo of this recipe the cover, or clears the cover with null. */
    public function setCover(?Photo $photo): void
    {
        abort_if($photo && $photo->recipe_id !== $this->id, 404);
        $this->cover_photo_id = $photo?->id;
        $this->save();
    }

    /** Only public recipes should appear in search engines. */
    public function isIndexable(): bool
    {
        return $this->visibility === self::PUBLIC;
    }

    /** Stores an uploaded image privately and queues its processing (see ProcessPhoto). */
    public function addPhoto(UploadedFile $file, array $attributes = []): Photo
    {
        $path = $file->storeAs('photos-raw/'.$this->id, Str::uuid().'.'.($file->extension() ?: 'jpg'), Photo::RAW_DISK);

        $photo = $this->photos()->create($attributes + [
            'disk_path' => $path,
            'position' => (int) $this->photos()->max('position') + 1,
        ]);
        ProcessPhoto::dispatch($photo->id);

        return $photo;
    }

    /** A private copy with the same content (not photos, rating or favourite) for making a variation. */
    public function duplicate(): self
    {
        $this->loadMissing(['categories', 'tags', 'ingredientGroups', 'ingredients', 'steps']);

        return DB::transaction(function () {
            $copy = $this->replicate(['slug', 'share_token', 'cover_photo_id', 'is_favorite', 'rating']);
            $copy->title = 'Copy of '.$this->title;
            $copy->visibility = self::PRIVATE;
            $copy->save();

            $copy->categories()->sync($this->categories->pluck('id'));
            $copy->tags()->sync($this->tags->pluck('id'));

            $groupMap = [];
            foreach ($this->ingredientGroups as $group) {
                $groupMap[$group->id] = $copy->ingredientGroups()->create($group->only(['name', 'position']))->id;
            }
            $copy->ingredients()->createMany($this->ingredients->map(fn ($i) => $i->only([
                'position', 'quantity', 'quantity_max', 'unit', 'name', 'note', 'original_text',
            ]) + ['ingredient_group_id' => $groupMap[$i->ingredient_group_id] ?? null])->all());
            $copy->steps()->createMany($this->steps->map->only(['position', 'body', 'timer_minutes'])->all());

            return $copy;
        });
    }

    public function shareUrl(): string
    {
        return route('shared.show', $this->share_token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function ingredientGroups(): HasMany
    {
        return $this->hasMany(IngredientGroup::class)->orderBy('position');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)->orderBy('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class)->orderBy('position');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('position')->orderBy('id');
    }

    public function cookLogs(): HasMany
    {
        return $this->hasMany(CookLog::class)->orderByDesc('cooked_on')->orderByDesc('id');
    }

    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    /** The chosen cover photo, or the first photo, as long as it has been processed. */
    public function cover(): ?Photo
    {
        return $this->coverPhoto?->isProcessed()
            ? $this->coverPhoto
            : $this->photos->first(fn (Photo $p) => $p->isProcessed());
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', self::PUBLIC);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('ingredients', fn (Builder $i) => $i->where('name', 'like', $like));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
