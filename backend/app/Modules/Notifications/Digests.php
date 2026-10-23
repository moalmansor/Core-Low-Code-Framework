<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserPreference;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Notification digests (specification §4.36, ADR-0063). A person who asks
 * for a daily or weekly digest gets, at 07:00 their time (Mondays for the
 * weekly one), one e-mail listing what is still unread in their notification
 * centre from the period. Nothing unread, nothing sent. Each person gets at
 * most one digest per period, whenever the scheduler runs.
 */
final class Digests
{
    public const HOUR = 7;

    private const MAX_ITEMS = 50;

    public function __construct(
        private readonly EmailQueue $emails,
        private readonly SettingsService $settings,
        private readonly TenantContext $tenant,
    ) {}

    /** Sends the digests that are due; returns how many were queued. */
    public function sendDue(?Carbon $now = null): int
    {
        $now ??= Carbon::now('UTC');
        $sent = 0;
        $default = (string) $this->settings->get('formats', 'timezone');
        UserPreference::query()->whereIn('digest_frequency', ['daily', 'weekly'])->orderBy('id')->chunkById(200, function ($prefs) use ($now, $default, &$sent): void {
            foreach ($prefs as $p) {
                $user = User::query()->withoutGlobalScope('organization')->whereKey($p->user_id)->where('status', 'active')->first();
                if ($user === null || ! $this->due($p, $now, $p->timezone ?: $default)) {
                    continue;
                }
                $this->tenant->set((int) $user->organization_id);
                $sent += $this->send($user, $p, $now) ? 1 : 0;
            }
        });

        return $sent;
    }

    /** Whether the person's digest is due now: their 07:00 hour (Monday for weekly), not sent in this period yet. */
    public function due(UserPreference $p, Carbon $now, string $timezone): bool
    {
        $local = $now->copy()->setTimezone($timezone);
        if ((int) $local->format('G') !== self::HOUR || ($p->digest_frequency === 'weekly' && (int) $local->format('N') !== 1)) {
            return false;
        }
        $last = $p->digest_sent_at === null ? null : Carbon::parse($p->digest_sent_at, 'UTC');

        return $last === null || $last->lt($now->copy()->subHours($p->digest_frequency === 'weekly' ? 24 * 7 - 2 : 22));
    }

    private function send(User $user, UserPreference $p, Carbon $now): bool
    {
        $since = $p->digest_sent_at !== null ? Carbon::parse($p->digest_sent_at, 'UTC') : $now->copy()->subDays($p->digest_frequency === 'weekly' ? 7 : 1);
        $items = DB::table('in_app_notifications')->where('user_id', $user->id)->whereNull('read_at')
            ->where('created_at', '>', $since->format('Y-m-d H:i:s.u'))->orderByDesc('created_at')->limit(self::MAX_ITEMS + 1)->get(['title', 'body', 'link']);
        $p->forceFill(['digest_sent_at' => $now->format('Y-m-d H:i:s.u')])->save();
        if ($items->isEmpty()) {
            return false;
        }
        $locale = $p->locale ?: (string) app()->getLocale();
        $base = rtrim((string) config('app.url'), '/');
        $rows = '';
        foreach ($items->take(self::MAX_ITEMS) as $n) {
            $title = e((string) $n->title);
            $link = is_string($n->link) && str_starts_with($n->link, '/') && ! str_starts_with($n->link, '//') ? $base.$n->link : null;
            $rows .= '<li style="margin:0 0 12px">'.($link !== null ? '<a href="'.e($link).'">'.$title.'</a>' : '<strong>'.$title.'</strong>')
                .($n->body !== null && $n->body !== '' ? '<br><span style="color:#555">'.e(mb_substr((string) $n->body, 0, 300)).'</span>' : '').'</li>';
        }
        $more = $items->count() > self::MAX_ITEMS ? '<p>'.e(__('notifications.digest_more', [], $locale)).'</p>' : '';
        $dir = in_array(substr($locale, 0, 2), ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
        $html = '<div dir="'.$dir.'" style="font-family:sans-serif;font-size:14px;line-height:1.5"><p>'.e(__('notifications.digest_intro', ['name' => $user->name], $locale)).'</p><ul style="padding-inline-start:20px">'
            .$rows.'</ul>'.$more.'<p><a href="'.e($base.'/').'">'.e(__('notifications.digest_open', [], $locale)).'</a></p></div>';
        $this->emails->enqueue([
            'source' => 'system', 'locale' => $locale, 'to' => [$user->email],
            'subject' => __($p->digest_frequency === 'weekly' ? 'notifications.digest_subject_weekly' : 'notifications.digest_subject_daily', ['n' => min($items->count(), self::MAX_ITEMS)], $locale),
            'html' => $html,
        ]);

        return true;
    }
}
