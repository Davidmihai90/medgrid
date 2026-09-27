<?php

namespace App\Domain\Health\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class SystemHealthService
{
    public function check(): array
    {
        return [
            'application' => $this->result(fn () => ['version' => app()->version()]),
            'database' => $this->result(fn () => ['driver' => DB::getDriverName(), 'database' => DB::selectOne('select current_database() as name')->name]),
            'postgis' => $this->result(fn () => ['version' => DB::selectOne('select PostGIS_Version() as version')->version]),
            'redis' => $this->result(function () {
                $pong = (string) app('redis')->connection()->ping();
                if (! str_contains(strtoupper($pong), 'PONG')) {
                    throw new \RuntimeException('Unexpected response');
                }

return ['client' => config('database.redis.client')];
            }),
            'queue' => $this->result(function () {
                if (config('queue.default') !== 'redis') {
                    throw new \RuntimeException('Redis queue is not configured');
                } app('redis')->connection('default')->ping();

                return ['connection' => 'redis'];
            }),
            'realtime' => $this->realtime(),
        ];
    }

    private function realtime(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return ['status' => 'UNAVAILABLE', 'message' => 'Reverb is not configured.'];
        }
        $host = config('reverb.servers.reverb.host', '127.0.0.1');
        if ($host === '0.0.0.0') {
            $host = '127.0.0.1';
        }
        $port = (int) config('reverb.servers.reverb.port', 8080);
        $socket = @fsockopen($host, $port, $errno, $error, 0.35);
        if (! $socket) {
            return ['status' => 'DEGRADED', 'message' => 'Configured; server is not reachable.'];
        }
        fclose($socket);

        return ['status' => 'HEALTHY', 'details' => ['transport' => 'reverb']];
    }

    private function result(callable $check): array
    {
        try {
            return ['status' => 'HEALTHY', 'details' => $check()];
        } catch (Throwable) {
            return ['status' => 'UNAVAILABLE', 'message' => 'Connectivity check failed.'];
        }
    }
}
