<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity as SpatieLogsActivity;

trait LogsActivity
{
    use SpatieLogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->logAttributes ?? [])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => $this->getDescriptionForEvent($eventName));
    }

    protected function getDescriptionForEvent(string $eventName): string
    {
        $modelName = class_basename($this);
        
        return match($eventName) {
            'created' => "Created new {$modelName}: {$this->getLogIdentifier()}",
            'updated' => "Updated {$modelName}: {$this->getLogIdentifier()}",
            'deleted' => "Deleted {$modelName}: {$this->getLogIdentifier()}",
            'restored' => "Restored {$modelName}: {$this->getLogIdentifier()}",
            default => "Performed {$eventName} on {$modelName}: {$this->getLogIdentifier()}",
        };
    }

    protected function getLogIdentifier(): string
    {
        return $this->name ?? $this->title ?? $this->id ?? $this->getKey();
    }
}