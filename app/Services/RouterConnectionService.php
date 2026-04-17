<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;

class RouterConnectionService
{
    public function test(string $host, int $port, string $username, string $password): array
    {
        try {
            $config = [
                'host' => $host,
                'user' => $username,
                'pass' => $password,
                'port' => $port,
                'timeout' => 5,
            ];

            if ($port === 8729) {
                $config['ssl'] = true;
                $config['ssl_options'] = [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ];
            }

            $client = new Client($config);

            $resource = $client->query(new Query('/system/resource/print'))->read();
            $identity = $client->query(new Query('/system/identity/print'))->read();
            $cloud = $client->query(new Query('/ip/cloud/print'))->read();

            return [
                'status' => 'online',
                'message' => 'Connection successful.',
                'data' => [
                    'model' => $resource[0]['board-name'] ?? null,
                    'version' => $resource[0]['version'] ?? null,
                    'identity' => $identity[0]['name'] ?? null,
                    'domain_name' => $cloud[0]['dns-name'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $status = str_contains($message, 'timed out')
                || str_contains($message, 'no route')
                || str_contains($message, 'refused')
                || str_contains($message, 'unreachable')
                ? 'unreachable'
                : 'offline';

            return [
                'status' => $status,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }
}
