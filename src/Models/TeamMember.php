<?php
namespace Susheelhbti\LaravelUserAdmin\Models;
use Illuminate\Database\Eloquent\Model;
class TeamMember extends Model {
    protected $table = 'user_admin_team_members';
    protected $fillable = ['team_id', 'user_id', 'role'];
    public function team() { return $this->belongsTo(Team::class); }
    public function user() { return $this->belongsTo(config('user_admin.user_model', \App\Models\User::class)); }
}
