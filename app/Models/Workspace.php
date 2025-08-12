<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';


    protected $fillable = [
        'id',
        'name',
        'slug',
        'user_id',
        'client_id',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }


    /**
     * Get the teams that belong to the workspace.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Team>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }


    public static function generateUniqueSlug(string $name, string $clientId): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (self::where('slug', $slug)->where('client_id', $clientId)->exists()) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        return $slug;
    }


    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($workspace) {
            if (empty($workspace->id)) {
                $workspace->id = (string) Str::uuid();
            }
            if (empty($workspace->slug)) {
                $workspace->slug = self::generateUniqueSlug($workspace->name, $workspace->client_id);
            }
        });
    }
}
