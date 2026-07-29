<?php

namespace App\Http\Controllers\Accurate;

use App\Http\Controllers\Controller;
use App\Models\AccurateConnection;
use App\Services\Accurate\AccurateClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccurateConnectionController extends Controller
{
    public function __construct(protected AccurateClient $client)
    {
    }

    public function redirectToAuthorize(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('accurate_oauth_state', $state);

        return redirect()->away($this->client->getAuthorizeUrl($state));
    }

    public function handleCallback(Request $request)
    {
        $expectedState = $request->session()->pull('accurate_oauth_state');

        if (! $expectedState || $request->query('state') !== $expectedState) {
            return response()->json(['message' => 'Invalid OAuth state.'], 400);
        }

        $request->validate(['code' => ['required', 'string']]);

        $connection = $this->client->exchangeCodeForToken($request->query('code'));

        return response()->json($connection->connectionStatus());
    }

    public function listDatabases()
    {
        return response()->json(['data' => $this->client->listDatabases()]);
    }

    public function selectDatabase(Request $request)
    {
        $validated = $request->validate([
            'id_db' => ['required', 'string'],
            'db_alias' => ['nullable', 'string'],
        ]);

        $connection = $this->client->selectDatabase($validated['id_db'], $validated['db_alias'] ?? null);

        return response()->json($connection->connectionStatus());
    }

    public function status()
    {
        return response()->json(AccurateConnection::singleton()->connectionStatus());
    }
}
