<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Team extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'slug',
        'workspace_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the workspace that owns the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Workspace, \App\Models\Team>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the permissions that belong to the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Permission>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'team_permission')
            ->withTimestamps();
    }

    /**
     * Generate a unique slug from the given name for a specific workspace.
     *
     * @param string $name
     * @param int $workspaceId
     * @return string
     */
    public static function generateUniqueSlug(string $name, int $workspaceId): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (self::where('slug', $slug)->where('workspace_id', $workspaceId)->exists()) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        return $slug;
    }

    /**
     * Get the client ID through the workspace relationship.
     *
     * @return string|null
     */
    public function getClientIdAttribute(): ?string
    {
        return $this->workspace?->client_id;
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($team) {
            if (empty($team->slug)) {
                $team->slug = self::generateUniqueSlug($team->name, $team->workspace_id);
            }
        });
    }
}
