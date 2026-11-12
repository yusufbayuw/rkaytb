<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditResourceLogger extends \MrAdder\FilamentLogger\Loggers\ResourceLogger
{
    public function updated(Model $model): void
    {
        if ($model instanceof User && $model->wasChanged('password')) {
            $this->log($model, 'Password Changed', properties: $this->buildProperties(
                $model, $model->getChanges(), $this->getPreviousAttributes($model, true),
                ['password_changed' => true],
            ));
            return;
        }
        parent::updated($model);
    }
}
