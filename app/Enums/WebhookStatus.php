<?php

namespace App\Enums;

enum WebhookStatus: string
{
    case Active = 'active';
    case Missing = 'missing';
    case Disabled = 'disabled';
    case Error = 'error';
    case Unregistered = 'unregistered';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
