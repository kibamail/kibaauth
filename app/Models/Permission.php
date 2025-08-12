<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Permission extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'description',
        'slug',
        'client_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the OAuth client that owns the permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Client, \App\Models\Permission>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the teams that have this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Team>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_permission')
            ->withTimestamps();
    }

    /**
     * Generate a unique slug from the given name for a specific client.
     *
     * @param string $name
     * @param string $clientId
     * @return string
     */
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

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($permission) {
            if (empty($permission->id)) {
                $permission->id = (string) Str::uuid();
            }
            if (!empty($permission->client_id)) {
                if (empty($permission->slug)) {
                    $permission->slug = self::generateUniqueSlug($permission->name, $permission->client_id);
                } else {
                    // If slug is provided, check if it's unique, but preserve exact format if available
                    if (self::where('slug', $permission->slug)->where('client_id', $permission->client_id)->exists()) {
                        $permission->slug = self::generateUniqueSlug($permission->slug, $permission->client_id);
                    }
                    // Otherwise keep the provided slug as-is
                }
            }
        });
    }
}
