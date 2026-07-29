<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccurateConnection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'session_id' => 'encrypted',
            'token_expires_at' => 'datetime',
            'session_created_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_refresh_attempted_at' => 'datetime',
        ];
    }

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function isTokenExpiring(int $bufferSeconds): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return now()->addSeconds($bufferSeconds)->greaterThanOrEqualTo($this->token_expires_at);
    }

    public function isSessionStale(int $ttlMinutes): bool
    {
        if (! $this->session_id || ! $this->session_created_at) {
            return true;
        }

        return $this->session_created_at->addMinutes($ttlMinutes)->isPast();
    }

    public function isConnected(): bool
    {
        return filled($this->access_token) && filled($this->id_db);
    }

    public function connectionStatus(): array
    {
        return [
            'connected' => $this->isConnected(),
            'id_db' => $this->id_db,
            'db_alias' => $this->db_alias,
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'last_refreshed_at' => $this->last_refreshed_at?->toIso8601String(),
            'last_refresh_error' => $this->last_refresh_error,
        ];
    }
}
