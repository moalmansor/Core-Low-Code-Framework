<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Notifications\Channels\ChannelDeliveries;
use App\Modules\Notifications\Channels\ChannelFailure;
use App\Modules\Notifications\Channels\ChannelProviders;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Support\Json\Canonical;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SMS and messaging channels (specification §4.33, ADR-0056). Their
 * settings hold provider credentials, so managing them needs "Manage
 * integrations"; whoever builds notification rules may list them to choose.
 */
final class NotificationChannelController extends Controller
{
    public function __construct(
        private readonly ChannelProviders $providers,
        private readonly ChannelDeliveries $deliveries,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_integrations');

        return response()->json(['data' => NotificationChannel::query()->orderBy('key')->get()->map(fn (NotificationChannel $c) => $this->summary($c))->values()->all(),
            'meta' => ['providers' => array_map(static fn (array $p) => array_map(static fn (array $f) => array_map(static fn (array $x) => ['required' => $x[0], 'secret' => $x[1]], $f), $p), ChannelProviders::PROVIDERS)]]);
    }

    /** The active channels a notification rule can choose, besides email and in-app. */
    public function options(): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_notifications', 'system.manage_integrations']), 403);

        return response()->json(['data' => NotificationChannel::query()->where('is_active', true)->whereIn('type', ChannelProviders::TYPES)->orderBy('key')->get()
            ->map(fn (NotificationChannel $c) => ['key' => $c->key, 'type' => $c->type, 'label' => $this->translator->labelOf($c->translationsFor('name'), $c->key)])->values()->all()]);
    }

    public function show(string $channel): JsonResponse
    {
        Gate::authorize('system.manage_integrations');

        return response()->json(['data' => $this->present($this->find($channel))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $data = $this->validated((array) $request->input('channel', []), null);
        $c = new NotificationChannel;
        DB::transaction(function () use ($c, $data): void {
            $c->forceFill($data['row'])->save();
            $c->setTranslations('name', $data['name']);
        });
        $this->audit->record('notification_channel.created', 'config', null, 'notification_channel', $c->id, ['key' => $c->key, 'type' => $c->type, 'provider' => $c->provider]);

        return response()->json(['data' => $this->present($c->refresh())], 201);
    }

    public function update(Request $request, string $channel): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $c = $this->find($channel);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->present($c)['hash'], (string) $request->input('base_hash')), 409, __('notifications.changed_meanwhile'));
        $data = $this->validated((array) $request->input('channel', []), $c);
        DB::transaction(function () use ($c, $data): void {
            $c->forceFill($data['row'])->save();
            $c->setTranslations('name', $data['name']);
        });
        $this->audit->record('notification_channel.updated', 'config', null, 'notification_channel', $c->id, ['key' => $c->key, 'provider' => $c->provider, 'active' => $c->is_active]);

        return response()->json(['data' => $this->present($c->refresh())]);
    }

    public function destroy(string $channel): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $c = $this->find($channel);
        $users = DB::table('notification_rules')->where('channels', 'like', '%"'.$c->key.'"%')->count();
        if ($users > 0) {
            throw ValidationException::withMessages(['channel' => __('notifications.channel.in_use', ['n' => $users])]);
        }
        DB::transaction(function () use ($c): void {
            // The delivery log stays (it names the channel); the channel is switched off and renamed rather than removed when it has one.
            if (DB::table('notification_deliveries')->where('notification_channel_id', $c->id)->exists()) {
                $c->forceFill(['is_active' => false, 'key' => mb_substr('_deleted_'.$c->id.'_'.$c->key, 0, 48), 'config' => []])->save();
            } else {
                $c->delete();
            }
        });
        $this->audit->record('notification_channel.deleted', 'config', null, 'notification_channel', $c->id, ['key' => $c->key]);

        return response()->json(null, 204);
    }

    /** Sends a test message now and reports the provider's answer. */
    public function test(Request $request, string $channel): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $c = $this->find($channel);
        $data = $request->validate(['to' => [ChannelProviders::isPerRecipient($c->type) ? 'required' : 'nullable', 'string', 'max:32', 'regex:/^\+[1-9][0-9]{6,14}$/']]);
        $to = (string) ($data['to'] ?? ($c->config['to'] ?? ''));
        $message = ['to' => $to, 'title' => __('notifications.channel.test_title'), 'text' => __('notifications.channel.test_text'), 'link' => ''];
        try {
            $answer = $this->providers->send($c->type, $c->provider, (array) $c->config, $message);
            $ok = true;
        } catch (ChannelFailure $e) {
            $answer = $e->getMessage();
            $ok = false;
        }
        DB::table('notification_channels')->where('id', $c->id)->update(['updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        $this->audit->record('notification_channel.tested', 'config', null, 'notification_channel', $c->id, ['key' => $c->key, 'ok' => $ok]);

        return response()->json(['data' => ['ok' => $ok, 'answer' => $answer]]);
    }

    /** The channel's delivery log, newest first. */
    public function deliveries(Request $request, string $channel): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $c = $this->find($channel);
        $status = $request->query('status');
        $q = DB::table('notification_deliveries')->where('notification_channel_id', $c->id)->orderByDesc('id');
        if (is_string($status) && in_array($status, ['pending', 'sent', 'failed', 'cancelled'], true)) {
            $q->where('status', $status);
        }
        $page = $q->paginate(25);

        return response()->json(['data' => collect($page->items())->map(static fn ($d) => [
            'id' => (int) $d->id, 'recipient' => ChannelProviders::isPerRecipient($c->type) ? self::maskNumber((string) $d->recipient) : (string) $d->recipient,
            'status' => $d->status, 'attempts' => (int) $d->attempts, 'error' => $d->error,
            'title' => (json_decode((string) $d->payload, true) ?: [])['title'] ?? null,
            'created_at' => Carbon::parse((string) $d->created_at, 'UTC')->toIso8601ZuluString(),
            'sent_at' => $d->sent_at === null ? null : Carbon::parse((string) $d->sent_at, 'UTC')->toIso8601ZuluString(),
        ])->values()->all(), 'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => 25]]);
    }

    public function retryDelivery(string $channel, int $delivery): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $c = $this->find($channel);
        abort_unless(DB::table('notification_deliveries')->where('id', $delivery)->where('notification_channel_id', $c->id)->exists(), 404);
        $this->deliveries->retry($delivery);

        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{row: array<string, mixed>, name: array<string, string>}
     *
     * @throws ValidationException
     */
    private function validated(array $d, ?NotificationChannel $existing): array
    {
        $errors = [];
        $key = (string) ($d['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1 || in_array($key, ['email', 'in_app'], true)) {
            $errors['key'][] = __('notifications.channel.bad_key');
        } elseif (NotificationChannel::query()->where('key', $key)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('notifications.channel.key_taken');
        } elseif ($existing !== null && $existing->key !== $key && DB::table('notification_rules')->where('channels', 'like', '%"'.$existing->key.'"%')->exists()) {
            $errors['key'][] = __('notifications.channel.key_in_use');
        }
        $type = (string) ($d['type'] ?? '');
        if (! in_array($type, ChannelProviders::TYPES, true)) {
            $errors['type'][] = __('notifications.channel.bad_type');
        } elseif ($existing !== null && $existing->type !== $type) {
            $errors['type'][] = __('notifications.channel.type_fixed');
        }
        $name = [];
        foreach ((array) ($d['name'] ?? []) as $loc => $v) {
            if (is_string($loc) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $loc) === 1 && is_string($v) && trim($v) !== '') {
                $name[$loc] = mb_substr(trim($v), 0, 255);
            }
        }
        if ($name === []) {
            $errors['name'][] = __('notifications.channel.name_required');
        }
        $limit = $d['rate_limit_per_minute'] ?? null;
        if ($limit !== null && (! is_int($limit) || $limit < 1 || $limit > 6000)) {
            $errors['rate_limit_per_minute'][] = __('notifications.channel.bad_limit');
        }
        $provider = (string) ($d['provider'] ?? '');
        $config = [];
        if ($errors === [] || ! isset($errors['type'])) {
            try {
                $config = $this->providers->normalize($type, $provider, (array) ($d['config'] ?? []), $existing !== null && $existing->provider === $provider ? (array) $existing->config : []);
            } catch (ValidationException $e) {
                $errors += $e->errors();
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['row' => ['key' => $key, 'type' => $type, 'provider' => $provider, 'config' => $config, 'rate_limit_per_minute' => $limit,
            'is_active' => (bool) ($d['active'] ?? true), 'is_default' => false], 'name' => $name];
    }

    /** @return array<string, mixed> */
    private function present(NotificationChannel $c): array
    {
        $doc = [
            'uuid' => strtolower($c->uuid), 'key' => $c->key, 'type' => $c->type, 'provider' => $c->provider,
            'config' => ChannelProviders::masked($c->type, $c->provider, (array) $c->config),
            'rate_limit_per_minute' => $c->rate_limit_per_minute, 'active' => $c->is_active, 'name' => (object) $c->translationsFor('name'),
        ];
        // The hash covers the saved secrets too (as a digest), so a secret changed elsewhere is noticed.
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc + ['secrets' => hash('sha256', (string) json_encode($c->config))]), JSON_UNESCAPED_UNICODE));

        return $doc + $this->summary($c);
    }

    /** @return array<string, mixed> */
    private function summary(NotificationChannel $c): array
    {
        $since = Carbon::now('UTC')->subDays(7)->format('Y-m-d H:i:s.u');
        $counts = DB::table('notification_deliveries')->where('notification_channel_id', $c->id)->where('created_at', '>=', $since)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'uuid' => strtolower($c->uuid), 'key' => $c->key, 'type' => $c->type, 'provider' => $c->provider, 'active' => $c->is_active,
            'label' => $this->translator->labelOf($c->translationsFor('name'), $c->key),
            'week' => ['sent' => (int) ($counts['sent'] ?? 0), 'failed' => (int) ($counts['failed'] ?? 0), 'pending' => (int) ($counts['pending'] ?? 0)],
        ];
    }

    private static function maskNumber(string $n): string
    {
        return strlen($n) <= 4 ? $n : str_repeat('•', strlen($n) - 4).substr($n, -4);
    }

    private function find(string $uuid): NotificationChannel
    {
        $c = Str::isUuid($uuid) ? NotificationChannel::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($c === null, 404);

        return $c;
    }
}
