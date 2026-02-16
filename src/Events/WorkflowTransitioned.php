<?php

declare(strict_types=1);

namespace Adichan\WorkflowEngine\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowTransitioned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Model $model,
        public string $transition,
        public string $fromState,
        public string $toState,
        public array $context = [],
	public ?\DateTimeInterface $performedAt = null  
	) {
		$this->performedAt = $performedAt ?? now();
	}
}
