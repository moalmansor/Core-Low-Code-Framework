<?php

declare(strict_types=1);

namespace App\Modules\Actions;

use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\EgressViolation;
use App\Modules\Audit\AuditWriter;
use App\Modules\Records\Templating\RenderContext;
use App\Modules\Records\Templating\TemplateValues;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * An action's "call a webhook" step (specification §4.15 "Outbound webhook
 * safety"). The request goes through the egress gateway (allowlisted hosts
 * only, blocked address ranges, pinned connection, no cross-host redirects,
 * timeout and response-size cap). Placeholders in the address and body are
 * filled with values the actor may see — URL-encoded in the address,
 * JSON-escaped in a JSON body. With a secret the request carries
 * `X-LCF-Timestamp` and `X-LCF-Signature: sha256=HMAC(secret, timestamp.body)`.
 * Network failures and 5xx/429 answers are retried up to three attempts with
 * backoff; every attempt is audited (host, method, status, time — never the
 * secret, header values or body).
 */
final class WebhookCaller
{
    public const ATTEMPTS = 3;

    public const TIMEOUT_SECONDS = 15;

    public const MAX_RESPONSE_BYTES = 1_048_576;

    /** @var \Closure(int): void */
    private \Closure $sleep;

    public function __construct(
        private readonly EgressGateway $egress,
        private readonly TemplateValues $values,
        private readonly AuditWriter $audit,
    ) {
        $this->sleep = static fn (int $ms) => usleep($ms * 1000);
    }

    /** For tests: replace waiting between attempts. @param  \Closure(int): void  $sleep */
    public function sleepWith(\Closure $sleep): void
    {
        $this->sleep = $sleep;
    }

    /**
     * @param  array{url: string, method: string, headers: list<array{name: string, value: string}>, body: string, format: string, secret: string|null}  $config
     * @param  array{form_id: int, record_id: int|null, user_id: int, action?: string, automation?: string}  $context
     * @return array{status: int, attempts: int}
     */
    public function call(array $config, RenderContext $ctx, array $context): array
    {
        $url = $this->values->url($config['url'], $ctx);
        $body = $config['method'] === 'GET' ? '' : $this->body($config['body'], $config['format'], $ctx);
        $headers = ['Content-Type' => match ($config['format']) {
            'form' => 'application/x-www-form-urlencoded', 'text' => 'text/plain; charset=utf-8', default => 'application/json',
        }, 'User-Agent' => 'LowCodeFramework-Webhook/1'];
        foreach ($config['headers'] as $h) {
            $headers[$h['name']] = $h['value'];
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $last = null;
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $started = microtime(true);
            $status = null;
            $error = null;
            try {
                if ($config['secret'] !== null) {
                    $ts = (string) time();
                    $headers['X-LCF-Timestamp'] = $ts;
                    $headers['X-LCF-Signature'] = 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $config['secret']);
                }
                $response = $this->egress->request($config['method'], $url, ['headers' => $headers, 'body' => $body], self::TIMEOUT_SECONDS, self::MAX_RESPONSE_BYTES);
                $status = $response->getStatusCode();
                $last = $response;
            } catch (EgressViolation $e) {
                // Not allowed is final: retrying cannot make it allowed.
                $this->log($context, $host, $config['method'], null, $attempt, $started, $e->getMessage());
                throw new StepFailed(__('actions.webhook_refused', ['host' => $host]), 0, $e);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
            $this->log($context, $host, $config['method'], $status, $attempt, $started, $error);
            $retry = $status === null || $status >= 500 || $status === 429;
            if (! $retry) {
                break;
            }
            if ($attempt < self::ATTEMPTS) {
                ($this->sleep)(500 * 2 ** ($attempt - 1));
            }
        }
        if (! $last instanceof ResponseInterface) {
            throw new StepFailed(__('actions.webhook_unreachable', ['host' => $host]));
        }
        if ($last->getStatusCode() >= 300) {
            throw new StepFailed(__('actions.webhook_failed', ['host' => $host, 'status' => $last->getStatusCode()]));
        }

        return ['status' => $last->getStatusCode(), 'attempts' => min($attempt, self::ATTEMPTS)];
    }

    /** The body with placeholders filled, escaped for its format. */
    private function body(string $template, string $format, RenderContext $ctx): string
    {
        return (string) preg_replace_callback(TemplateValues::TOKEN, function (array $m) use ($format, $ctx): string {
            $v = $this->values->value(strtolower($m[1]), $ctx);

            return match ($format) {
                'json' => substr((string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1),
                'form' => rawurlencode($v),
                default => $v,
            };
        }, $template);
    }

    /** @param  array{form_id: int, record_id: int|null, user_id: int, action?: string, automation?: string}  $context */
    private function log(array $context, string $host, string $method, ?int $status, int $attempt, float $started, ?string $error): void
    {
        $automation = $context['automation'] ?? null;
        $this->audit->record($automation === null ? 'action.webhook_called' : 'automation.webhook_called', 'operations', null, $automation === null ? 'action' : 'automation', null, [
            ($automation === null ? 'action' : 'automation') => $automation ?? ($context['action'] ?? null), 'host' => $host, 'method' => $method, 'status' => $status, 'attempt' => $attempt,
            'ms' => (int) round((microtime(true) - $started) * 1000), 'error' => $error === null ? null : mb_substr($error, 0, 300),
        ], $context['user_id'], null, $context['form_id'], $context['record_id']);
    }
}
