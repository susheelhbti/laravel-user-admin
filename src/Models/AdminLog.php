<?php

namespace Susheelhbti\LaravelUserAdmin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model
{
    use HasFactory;

    protected $table = 'otpguard_admin_logs';

    protected $fillable = [
        'admin_id', 'target_user_id', 'action', 'details', 'ip_address',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function admin()
    {
        $userModel = config('user_admin.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'admin_id');
    }

    public function targetUser()
    {
        $userModel = config('user_admin.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'target_user_id');
    }
}
