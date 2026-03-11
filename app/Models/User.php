<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable {
    use HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'api_token',
        'photo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string {
        if (!$this->photo) return null;
        return url('storage/' . $this->photo);
    }

    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function driver() {
        return $this->hasOne(Driver::class);
    }

    public function student() {
        return $this->hasOne(Student::class);
    }
}
