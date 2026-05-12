<?php

namespace Susheelhbti\LaravelUserAdmin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'otpguard_roles';

    protected $fillable = ['name', 'slug', 'description'];

    public function users()
    {
        $userModel = config('user_admin.user_model', \App\Models\User::class);

        return $this->belongsToMany($userModel, 'otpguard_role_user', 'role_id', 'user_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'otpguard_role_permission', 'role_id', 'permission_id');
    }
}
