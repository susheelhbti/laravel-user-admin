<?php
namespace Susheelhbti\LaravelUserAdmin\Models;
use Illuminate\Database\Eloquent\Model;
class GdprConsent extends Model {
    protected $table = 'user_admin_gdpr_consents';
    protected $fillable = ['user_id', 'consent_type', 'granted', 'ip_address'];
    protected $casts = ['granted' => 'boolean'];
    public function user() { return $this->belongsTo(config('user_admin.user_model', \App\Models\User::class)); }
}
