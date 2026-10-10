<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Core\I18n\Translator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes in-app notifications (specification §4.16, architecture §19.14).
 * Titles and bodies are rendered in each recipient's language when written,
 * from message keys of the server catalog, so the notifications center never
 * shows another user's language. A recipient who switched the in-app channel
 * off receives nothing.
 */
final class InAppNotifier
{
    public function __construct(private readonly Translator $translator) {}

    /**
     * @param  array<string, scalar|null>  $params  message parameters (names, counts; never identifiers)
     * @param  array<string, mixed>|null  $data
     * @param  array{form_id?: int|null, record_id?: int|null, rule_id?: int|null, on_behalf_of?: int|null, body?: string|null, body_params?: array<string, scalar|null>}  $context
     */
    public function notify(int $userId, string $type, string $titleKey, array $params = [], ?string $link = null, ?array $data = null, array $context = []): ?int
    {
        $prefs = DB::table('user_preferences')->where('user_id', $userId)->first(['locale', 'notification_channels']);
        $channels = json_decode((string) ($prefs->notification_channels ?? ''), true);
        if (is_array($channels) && ($channels['in_app'] ?? true) === false) {
            return null;
        }
        $org = DB::table('users')->where('id', $userId)->value('organization_id');
        if ($org === null) {
            return null;
        }
        $locale = $prefs->locale ?? $this->translator->defaultLocale();
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');

        return (int) DB::table('in_app_notifications')->insertGetId([
            'uuid' => (string) Str::uuid7(),
            'organization_id' => (int) $org,
            'user_id' => $userId,
            'type' => $type,
            'title' => mb_substr((string) __($titleKey, $params, $locale), 0, 255),
            'body' => isset($context['body']) ? (string) __($context['body'], $context['body_params'] ?? [], $locale) : null,
            'link' => $link,
            'data' => $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'notification_rule_id' => $context['rule_id'] ?? null,
            'form_id' => $context['form_id'] ?? null,
            'record_id' => $context['record_id'] ?? null,
            'on_behalf_of_user_id' => $context['on_behalf_of'] ?? null,
            'created_at' => $now,
        ]);
    }
}
