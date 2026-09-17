<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function index()
    {
        return view('campus-dashboard');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'business_type' => ['required', 'string', 'max:255'],
        ]);

        return 'Generated successfully';
    }

    public function dashboard()
    {
        $endpoint = config('services.google.dashboard_endpoint');
        $token = config('services.google.dashboard_token');

        if (!$endpoint || !$token) {
            return response()->json(['error' => 'Google Sheets dashboard is not configured.'], 503);
        }

        $cachedPayload = Cache::get('google.dashboard.payload');
        $cachedAt = Cache::get('google.dashboard.fetched_at');
        if (is_array($cachedPayload) && is_numeric($cachedAt) && (time() - (int) $cachedAt) < 5) {
            return response()->json($cachedPayload)
                ->header('Cache-Control', 'no-store, private');
        }

        try {
            $response = $this->googleClient()->get($endpoint, ['query' => ['token' => $token]]);
            $payload = json_decode($response->getBody()->getContents(), true) ?: [];
            if ($response->getStatusCode() >= 400 || isset($payload['error'])) {
                return response()->json([
                    'error' => $payload['error'] ?? 'Google Sheets dashboard is unavailable.',
                ], 502);
            }

            Cache::put('google.dashboard.payload', $payload, now()->addMinutes(5));
            Cache::put('google.dashboard.fetched_at', time(), now()->addMinutes(5));

            return response()->json($payload, $response->getStatusCode())
                ->header('Cache-Control', 'no-store, private');
        } catch (\Throwable $exception) {
            Log::error('Google Sheets dashboard request failed', ['message' => $exception->getMessage()]);

            $cachedPayload = Cache::get('google.dashboard.payload');
            if (is_array($cachedPayload)) {
                $cachedPayload['sync_warning'] = 'Showing the last successful Google Sheets sync.';
                return response()->json($cachedPayload)
                    ->header('Cache-Control', 'no-store, private');
            }

            return response()->json(['error' => 'Google Sheets dashboard is unavailable.'], 503)
                ->header('Cache-Control', 'no-store, private');
        }
    }

    public function visitorStatus(Request $request)
    {
        $data = $request->validate([
            'visitor_id' => ['required', 'string', 'regex:/^VIS-\d{6}$/i'],
            'action' => ['required', 'in:checkin,checkout,accountability'],
            'accountability' => ['nullable', 'in:ACCOUNTED,UNACCOUNTED', 'required_if:action,accountability'],
        ]);
        $endpoint = config('services.google.dashboard_endpoint');
        $token = config('services.google.dashboard_token');

        if (!$endpoint || !$token) {
            return response()->json(['error' => 'Google Sheets endpoint is not configured.'], 503);
        }

        try {
            $response = $this->googleClient()->post($endpoint . '?token=' . urlencode($token), ['json' => $data]);
            $payload = json_decode($response->getBody()->getContents(), true) ?: [];
            if ($response->getStatusCode() >= 400 || isset($payload['error']) || !($payload['ok'] ?? false)) {
                return response()->json([
                    'error' => $payload['error'] ?? 'Google Sheets did not confirm the visitor status update.',
                ], 502);
            }

            Cache::forget('google.dashboard.payload');
            Cache::forget('google.dashboard.fetched_at');

            return response()->json($payload, $response->getStatusCode())
                ->header('Cache-Control', 'no-store, private');
        } catch (\Throwable $exception) {
            return response()->json(['error' => 'Google Sheets update is unavailable.'], 503);
        }
    }

    public function visitorPass(Request $request)
    {
        $data = $request->validate([
            'visitor_id' => ['required', 'string', 'regex:/^VIS-\d{6}$/i'],
            'action' => ['required', 'in:lookup,email'],
            'email' => ['nullable', 'email', 'max:255', 'required_if:action,email'],
        ]);

        $endpoint = config('services.google.dashboard_endpoint');
        $token = config('services.google.dashboard_token');

        if (!$endpoint || !$token) {
            return response()->json(['error' => 'Google Sheets endpoint is not configured.'], 503);
        }

        try {
            $query = [
                'token' => $token,
                'visitor_id' => $data['visitor_id'],
            ];

            if ($data['action'] === 'lookup') {
                $response = $this->googleClient()->get($endpoint, ['query' => $query]);
            } else {
                $response = $this->googleClient()->post($endpoint . '?token=' . urlencode($token), ['json' => [
                    'action' => 'email',
                    'visitor_id' => $data['visitor_id'],
                    'email' => $data['email'],
                ]]);
            }

            $payload = json_decode($response->getBody()->getContents(), true) ?: [];
            if ($data['action'] === 'email' && ($response->getStatusCode() >= 400 || isset($payload['error']) || !($payload['ok'] ?? false))) {
                return response()->json([
                    'error' => $payload['error'] ?? 'Google Apps Script did not confirm that the email was sent.',
                ], 502);
            }

            return response()->json($payload, $response->getStatusCode())
                ->header('Cache-Control', 'no-store, private');
        } catch (\Throwable $exception) {
            return response()->json(['error' => 'Google Sheets pass service is unavailable.'], 503);
        }
    }

    private function googleClient(): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client([
            'handler' => \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\StreamHandler()),
            'allow_redirects' => true,
            'connect_timeout' => 8,
            'timeout' => 20,
        ]);
    }
}
