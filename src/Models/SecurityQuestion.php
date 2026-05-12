<?php
namespace Susheelhbti\LaravelUserAdmin\Models;
use Illuminate\Database\Eloquent\Model;
class SecurityQuestion extends Model {
    protected $table = 'user_admin_security_questions';
    protected $fillable = ['user_id', 'question', 'answer'];
    protected $hidden = ['answer'];
    public function user() { return $this->belongsTo(config('user_admin.user_model', \App\Models\User::class)); }
}
