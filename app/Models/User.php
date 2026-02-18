<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'nik',
        'name',
        'email',
        'photo_url',
        'keycloak_id',
        'password'
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return Auth::check();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return null;
    }

    public function getFilamentName(): string
    {
        $name = $this->name ?? 'User';
        return "{$name} ({$this->id})";
    }

}
