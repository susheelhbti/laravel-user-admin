<?php

namespace Susheelhbti\LaravelUserAdmin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    use HasFactory;

    protected $table = 'otpguard_login_histories';

    protected $fillable = [
        'user_id', 'ip_address', 'user_agent', 'location',
    ];

    public function user()
    {
        $userModel = config('user_admin.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel);
    }
}
