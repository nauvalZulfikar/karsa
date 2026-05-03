<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaGatewayService
{
    private string $provider;
    private string $token;

    public function __construct()
    {
        $this->provider = config('services.wa_gateway.provider', 'fonnte');
        $this->token    = config('services.wa_gateway.token', '');
    }

    public function kirim(string $nomorHp, string $pesan): bool
    {
        $nomor = $this->formatNomor($nomorHp);
        if (!$nomor || !$this->token) {
            Log::info('WA skip: token kosong atau nomor invalid', ['nomor' => $nomorHp]);
            return false;
        }

        try {
            return match($this->provider) {
                'fonnte' => $this->kirimFonnte($nomor, $pesan),
                'wablas' => $this->kirimWablas($nomor, $pesan),
                default  => false,
            };
        } catch (\Throwable $e) {
            Log::error('WA gagal kirim', ['nomor' => $nomor, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function kirimFonnte(string $nomor, string $pesan): bool
    {
        $response = Http::withHeaders(['Authorization' => $this->token])
            ->timeout(15)
            ->post('https://api.fonnte.com/send', [
                'target'  => $nomor,
                'message' => $pesan,
            ]);

        $ok = $response->successful() && ($response->json('status') === true);
        if (!$ok) {
            Log::warning('Fonnte response tidak OK', ['body' => $response->body()]);
        }
        return $ok;
    }

    private function kirimWablas(string $nomor, string $pesan): bool
    {
        $response = Http::withToken($this->token)
            ->timeout(15)
            ->post('https://console.wablas.com/api/send-message', [
                'phone'   => $nomor,
                'message' => $pesan,
            ]);

        $ok = $response->successful() && ($response->json('status') === true);
        if (!$ok) {
            Log::warning('Wablas response tidak OK', ['body' => $response->body()]);
        }
        return $ok;
    }

    private function formatNomor(string $nomor): ?string
    {
        $nomor = preg_replace('/\D/', '', $nomor);
        if (str_starts_with($nomor, '0')) {
            $nomor = '62' . substr($nomor, 1);
        }
        if (str_starts_with($nomor, '+')) {
            $nomor = substr($nomor, 1);
        }
        if (strlen($nomor) < 10 || strlen($nomor) > 15) {
            return null;
        }
        return $nomor;
    }
}
