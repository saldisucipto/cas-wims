<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'role', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    public function validatedRequests()
    {
        return $this->hasMany(ConsumableRequest::class, 'validated_by');
    }

    public function rejectedRequests()
    {
        return $this->hasMany(ConsumableRequest::class, 'rejected_by');
    }

    public function atkRequests()
    {
        return $this->hasMany(AtkRequest::class, 'requested_by');
    }

    public function approvedAtkRequests()
    {
        return $this->hasMany(AtkRequest::class, 'approved_by');
    }

    public function rejectedAtkRequests()
    {
        return $this->hasMany(AtkRequest::class, 'rejected_by');
    }

    public function forceClosedWorkingSessions()
    {
        return $this->hasMany(WorkingSession::class, 'force_closed_by');
    }
}
