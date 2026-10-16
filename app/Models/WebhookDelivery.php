<?php

namespace App\Models;

use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $form_id
 * @property Carbon $received_at
 * @property bool $signature_valid
 * @property string|null $event_id
 * @property string|null $event_type
 * @property string|null $response_token
 * @property string $payload
 * @property Carbon|null $processed_at
 * @property string|null $error
 * @property-read Form|null $form
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    protected $fillable = ['form_id', 'received_at', 'signature_valid', 'event_id', 'event_type', 'response_token', 'payload', 'processed_at', 'error'];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'signature_valid' => 'boolean',
        ];
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class)->withTrashed();
    }

    public function decodedPayload(): array
    {
        $decoded = json_decode($this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
