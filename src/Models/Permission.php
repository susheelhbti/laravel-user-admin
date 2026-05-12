<?php

namespace Susheelhbti\LaravelUserAdmin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'otpguard_permissions';

    protected $fillable = ['name', 'slug', 'group'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'otpguard_role_permission', 'permission_id', 'role_id');
    }

    public function users()
    {
        $userModel = config('user_admin.user_model', \App\Models\User::class);

        return $this->belongsToMany($userModel, 'otpguard_permission_user', 'permission_id', 'user_id');
    }
}
