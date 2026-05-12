<?php
namespace Susheelhbti\LaravelUserAdmin\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Team extends Model {
    use SoftDeletes;
    protected $table = 'user_admin_teams';
    protected $fillable = ['owner_id', 'name', 'slug', 'settings'];
    protected $casts = ['settings' => 'array'];
    public function owner() { return $this->belongsTo(config('user_admin.user_model', \App\Models\User::class), 'owner_id'); }
    public function members() { return $this->hasMany(TeamMember::class, 'team_id'); }
    public function users() { $m = config('user_admin.user_model', \App\Models\User::class); return $this->belongsToMany($m, 'user_admin_team_members', 'team_id', 'user_id')->withPivot('role')->withTimestamps(); }
}
