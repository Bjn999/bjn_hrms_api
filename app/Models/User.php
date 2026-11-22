<?php

namespace App\Models;

use App\Traits\HasTenant;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasTenant;
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'company_id',
        'name',
        'email',
        'username',
        'password',
        'initial_password',
        'is_active',
        'role',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function getNameAttribute($value)
    {
        if ($this->employee_id && $this->relationLoaded('employee') && $this->employee) {
            return $this->employee->emp_name;
        } elseif ($this->employee_id) {
            // If employee relation is not loaded but exists, fetch it or just fallback to db name
            // It's better to rely on eagerly loaded relations to avoid N+1 queries.
            // If not loaded, we just return the name field from users table to be safe.
            return $value;
        }
        return $value;
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'initial_password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
