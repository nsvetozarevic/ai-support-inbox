<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\AiCircuitOpenReason;
use Exception;
use Throwable;

class AiCircuitOpenException extends Exception
{
    public bool $providerWide;

    public function __construct(
        public string $provider,
        public ?string $model,
        public AiCircuitOpenReason $reason,
        public int $until,
        ?Throwable $previous = null,
    ) {
        $this->providerWide = $model === null;

        $subject = $model === null ? $provider : $provider.' '.$model;

        parent::__construct("AI circuit is open for {$subject} ({$reason->label()}).", previous: $previous);
    }
}
