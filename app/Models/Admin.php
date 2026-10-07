<?php

namespace App\Models;

use App\Enums\AdminRole;
use Database\Factories\AdminFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

/*
 * Someone who runs the shop. Customers are App\Models\User, in their own table
 * and signed in through their own guard: an admin account cannot buy, and a
 * customer account cannot open the admin.
 */
class Admin extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => AdminRole::class,
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        /* The shop must always have someone who can manage the team. */
        static::updating(function (Admin $admin) {
            if ($admin->isDirty('role')
                && $admin->getOriginal('role') === AdminRole::SuperAdmin
                && $admin->role !== AdminRole::SuperAdmin
                && $admin->isLastSuperAdmin()) {
                throw ValidationException::withMessages([
                    'role' => 'This is the only super admin. Make someone else a super admin first.',
                ]);
            }
        });

        static::deleting(function (Admin $admin) {
            if ($admin->isLastSuperAdmin()) {
                throw ValidationException::withMessages([
                    'admin' => 'The only super admin cannot be removed.',
                ]);
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === AdminRole::SuperAdmin;
    }

    public function isLastSuperAdmin(): bool
    {
        return $this->getOriginal('role') === AdminRole::SuperAdmin
            && static::query()->superAdmins()->whereKeyNot($this->getKey())->doesntExist();
    }

    public function initials(): string
    {
        return self::initialsFor($this->name);
    }

    /* For names kept on the activity log after the admin themselves is gone. */
    public static function initialsFor(?string $name): string
    {
        return collect(preg_split('/\s+/', trim((string) $name)))
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('') ?: '?';
    }

    public function scopeSuperAdmins(Builder $query): Builder
    {
        return $query->where('role', AdminRole::SuperAdmin->value);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('created_at');
    }

    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }
}
