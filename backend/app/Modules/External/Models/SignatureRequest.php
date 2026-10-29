<?php

declare(strict_types=1);

namespace App\Modules\External\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * A request to sign a record's document (specification §4.32, ADR-0058):
 * the signer opens a tokenized link, reviews the generated document, draws
 * a signature or declines; the signed PDF is stored with its hash.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property Carbon|null $created_at
 * @property int|null $created_by
 * @property int $form_id
 * @property int $record_id
 * @property string $signer_type
 * @property int|null $signer_user_id
 * @property int|null $signer_external_user_id
 * @property string|null $signer_email
 * @property int|null $access_token_id
 * @property int|null $document_template_id
 * @property string $status
 * @property int|null $signature_file_id
 * @property int|null $signed_file_id
 * @property string|null $signed_document_hash
 * @property Carbon|null $signed_at
 * @property string|null $signer_ip
 * @property string|null $signer_user_agent
 * @property string|null $decline_reason
 * @property Carbon $expires_at
 */
final class SignatureRequest extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $table = 'signature_requests';

    protected function casts(): array
    {
        return ['signed_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
