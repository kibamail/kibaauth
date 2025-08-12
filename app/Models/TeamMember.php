<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
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
        'team_id',
        'user_id',
        'email',
        'status',
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
            'team_id' => 'string',
            'user_id' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the team that the member belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, \App\Models\TeamMember>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that is the team member.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\TeamMember>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the team member is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the team member is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the team member is associated with a registered user.
     *
     * @return bool
     */
    public function hasUser(): bool
    {
        return !is_null($this->user_id);
    }

    /**
     * Check if the team member is email-only (invited but not registered).
     *
     * @return bool
     */
    public function isEmailOnly(): bool
    {
        return is_null($this->user_id) && !is_null($this->email);
    }

    /**
     * Get the display name for the team member.
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->hasUser()) {
            return $this->user->email;
        }

        return $this->email ?? 'Unknown';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($teamMember) {
            if (empty($teamMember->id)) {
                $teamMember->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
