<?php

declare(strict_types=1);

namespace App\Providers;

use App\Data\ChunkSeparatorData;
use App\Data\HandbookChunkingData;
use App\Utilities\HandbookChunker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HandbookChunker::class, function (): HandbookChunker {
            return new HandbookChunker(new HandbookChunkingData(
                target: (int) config('handbook.chunking.target'),
                window: (int) config('handbook.chunking.window'),
                overlap: (int) config('handbook.chunking.overlap'),
                separators: $this->handbookSeparators(),
            ));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('tickets', fn (Request $request): Limit => Limit::perHour(10)->by($request->ip()));
    }

    /**
     * @return list<ChunkSeparatorData>
     */
    private function handbookSeparators(): array
    {
        return collect(config('handbook.separators'))
            ->map(fn (array $separator): ChunkSeparatorData => new ChunkSeparatorData(
                value: $separator['value'],
                offset: (int) $separator['offset'],
            ))
            ->all();
    }
}
