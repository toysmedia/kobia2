<?php

namespace App\Jobs;

use App\Models\Router;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshAllRouterStatusesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'router-status';

    public function handle(): void
    {
        Router::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $routerId) => CheckRouterStatusJob::dispatch($routerId)->onQueue('router-status'));
    }
}
