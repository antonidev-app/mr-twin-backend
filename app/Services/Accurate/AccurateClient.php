<?php

namespace App\Services\Accurate;

use App\Exceptions\Accurate\AccurateApiException;
use App\Exceptions\Accurate\AccurateNotConnectedException;
use App\Exceptions\Accurate\AccurateOAuthException;
use App\Models\AccurateConnection;
use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccurateClient
{
    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        protected string $redirectUri,
        protected string $accountBaseUrl,
        protected string $scopes,
        protected int $tokenExpiryBuffer,
        protected int $sessionTtlMinutes,
    ) {
    }

    public function getAuthorizeUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => $this->scopes,
            'state' => $state,
        ]);

        return "{$this->accountBaseUrl}/oauth/authorize?{$query}";
    }

    public function exchangeCodeForToken(string $code): AccurateConnection
    {
        $json = $this->postToken([
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ], 'accurate.oauth.exchange_failed');

        $connection = AccurateConnection::singleton();
        $this->applyTokenResponse($connection, $json);

        return $connection;
    }

    public function ensureFreshToken(): AccurateConnection
    {
        $connection = AccurateConnection::singleton();

        if (! $connection->isTokenExpiring($this->tokenExpiryBuffer)) {
            return $connection;
        }

        return Cache::lock('accurate:oauth:refresh', 15)->block(10, fn () => $this->refreshToken());
    }

    public function listDatabases(): array
    {
        $connection = $this->ensureFreshToken();

        $response = Http::withHeaders($this->bearerHeaders($connection))
            ->get("{$this->accountBaseUrl}/api/db-list.do");

        $json = $response->json() ?? [];

        if (isset($json['error'])) {
            Log::error('accurate.databases.list_failed', ['error' => $json]);
            throw new AccurateOAuthException($json['error_description'] ?? $json['error']);
        }

        return $json['d'] ?? [];
    }

    public function selectDatabase(string $idDb, ?string $dbAlias = null): AccurateConnection
    {
        $connection = $this->ensureFreshToken();
        $connection->update(['id_db' => $idDb, 'db_alias' => $dbAlias]);

        $this->openSession($connection->fresh());

        return $connection->fresh();
    }

    public function ensureSession(): AccurateConnection
    {
        $connection = $this->ensureFreshToken();

        if (! $connection->id_db) {
            throw new AccurateNotConnectedException('No Accurate database selected yet.');
        }

        if ($connection->isSessionStale($this->sessionTtlMinutes)) {
            $this->openSession($connection);
            $connection = $connection->fresh();
        }

        return $connection;
    }

    public function request(string $method, string $endpoint, array $query = [], array $form = []): array
    {
        $connection = $this->ensureSession();

        $json = $this->sendDataRequest($connection, $method, $endpoint, $query, $form);

        // Accurate doesn't document a stale-session error signature; treat an
        // {s:false} with no error detail as a signal to retry once with a
        // freshly opened session before giving up (see plan Open Question #3).
        if (($json['s'] ?? null) === false && empty($json['d']) && ! isset($json['error'])) {
            $this->openSession($connection);
            $connection = $connection->fresh();
            $json = $this->sendDataRequest($connection, $method, $endpoint, $query, $form);
        }

        if (isset($json['error'])) {
            Log::error('accurate.api.oauth_error', ['endpoint' => $endpoint, 'error' => $json]);
            throw new AccurateOAuthException($json['error_description'] ?? $json['error']);
        }

        if (($json['s'] ?? null) === false) {
            $message = is_array($json['d'] ?? null) ? ($json['d'][0] ?? 'Accurate API error') : ($json['d'] ?? 'Accurate API error');
            Log::error('accurate.api.error', ['endpoint' => $endpoint, 'response' => $json]);
            throw new AccurateApiException($message, $json['d'] ?? null);
        }

        return $json;
    }

    public function paginate(string $endpoint, array $fields, array $filters = [], int $pageSize = 100): Generator
    {
        $page = 1;
        $pageCount = 1;

        do {
            $query = array_merge($filters, [
                'fields' => implode(',', $fields),
                'sp.page' => $page,
                'sp.pageSize' => $pageSize,
            ]);

            $json = $this->request('GET', $endpoint, $query);

            yield $json['d'] ?? [];

            $pageCount = $json['sp']['pageCount'] ?? $page;
            $page++;
        } while ($page <= $pageCount);
    }

    protected function refreshToken(): AccurateConnection
    {
        $connection = AccurateConnection::singleton()->fresh();

        // Double-checked: someone else may have already refreshed while we
        // were waiting for the lock, using the refresh_token we'd otherwise
        // resend (Accurate rotates refresh tokens; reuse triggers revocation).
        if (! $connection->isTokenExpiring($this->tokenExpiryBuffer)) {
            return $connection;
        }

        $connection->update(['last_refresh_attempted_at' => now()]);

        try {
            $json = $this->postToken([
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ], 'accurate.oauth.refresh_failed', $connection);
        } catch (AccurateOAuthException $e) {
            $connection->update(['last_refresh_error' => $e->getMessage()]);
            throw $e;
        }

        $this->applyTokenResponse($connection, $json);

        return $connection;
    }

    protected function postToken(array $body, string $logEvent, ?AccurateConnection $connectionForError = null): array
    {
        $response = Http::asForm()->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.base64_encode("{$this->clientId}:{$this->clientSecret}"),
        ])->post("{$this->accountBaseUrl}/oauth/token", $body);

        $json = $response->json() ?? [];

        if (isset($json['error'])) {
            Log::error($logEvent, ['error' => $json]);
            throw new AccurateOAuthException($json['error_description'] ?? $json['error']);
        }

        return $json;
    }

    protected function applyTokenResponse(AccurateConnection $connection, array $json): void
    {
        $connection->update([
            'access_token' => $json['access_token'],
            'refresh_token' => $json['refresh_token'],
            'token_expires_at' => now()->addSeconds(max(($json['expires_in'] ?? 0) - $this->tokenExpiryBuffer, 0)),
            'granted_scopes' => $json['scope'] ?? $connection->granted_scopes,
            'last_refreshed_at' => now(),
            'last_refresh_error' => null,
        ]);
    }

    protected function openSession(AccurateConnection $connection): void
    {
        $response = Http::withHeaders($this->bearerHeaders($connection))
            ->get("{$this->accountBaseUrl}/api/open-db.do", ['id' => $connection->id_db]);

        $json = $response->json() ?? [];

        if (isset($json['error'])) {
            Log::error('accurate.session.open_failed', ['error' => $json]);
            throw new AccurateOAuthException($json['error_description'] ?? $json['error']);
        }

        $connection->update([
            'session_id' => $json['session'] ?? null,
            'session_host' => $json['host'] ?? null,
            'session_created_at' => now(),
        ]);
    }

    protected function sendDataRequest(AccurateConnection $connection, string $method, string $endpoint, array $query, array $form): array
    {
        $url = rtrim((string) $connection->session_host, '/')."/accurate/api/{$endpoint}";

        $request = Http::asForm()->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$connection->access_token,
            'X-Session-ID' => $connection->session_id,
        ]);

        $response = strtoupper($method) === 'GET'
            ? $request->get($url, $query)
            : $request->post($url, array_merge($query, $form));

        return $response->json() ?? [];
    }

    protected function bearerHeaders(AccurateConnection $connection): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$connection->access_token,
        ];
    }
}
