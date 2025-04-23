<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone_number',
        'email_verified_at', // Ditambahkan untuk verifikasi email
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the nasabah profile associated with the user.
     */
    public function nasabah()
    {
        return $this->hasOne(Nasabah::class);
    }

    /**
     * Check if user has completed the nasabah profile.
     */
    public function hasCompleteNasabahProfile()
    {
        return $this->nasabah && $this->nasabah->isProfileComplete();
    }

    /**
     * Get the current profile completion status.
     * 
     * @return string
     */
    public function getProfileStatus()
    {
        if (!$this->nasabah) {
            return 'not_started';
        }
        
        if ($this->nasabah->isProfileComplete()) {
            return 'complete';
        }
        
        if ($this->nasabah->isPartThreeComplete()) {
            return 'step3_complete';
        }
        
        if ($this->nasabah->isPartTwoComplete()) {
            return 'step2_complete';
        }
        
        if ($this->nasabah->isPartOneComplete()) {
            return 'step1_complete';
        }
        
        return 'started';
    }

    /**
     * Check if the user has a specific role.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * Check if the user is an admin.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user is a nasabah.
     *
     * @return bool
     */
    public function isNasabah()
    {
        return $this->role === 'nasabah';
    }

    /**
     * Check if the user is a mitra.
     *
     * @return bool
     */
    public function isMitra()
    {
        return $this->role === 'mitra';
    }

    /**
     * Check if the user is from industri.
     *
     * @return bool
     */
    public function isIndustri()
    {
        return $this->role === 'industri';
    }

    /**
     * Check if the user is from pemerintah.
     *
     * @return bool
     */
    public function isPemerintah()
    {
        return $this->role === 'pemerintah';
    }
}