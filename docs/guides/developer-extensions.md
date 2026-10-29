# Developer extension guide

Almost everything is built without code: forms, rules, formulas, workflows,
actions, automations, reports, integrations. Extensions are for the rare case
the builder cannot express (specification §4.19). They need the **Manage
code** permission to write and the **Approve code** permission, held by
someone else, to approve. The design is ADR-0051.

Open **Admin Console → Developer extensions**.

## 1. Kinds of extension

| Type | Runs | Use it for |
|---|---|---|
| Server hook | On the server, inside the save, at one point of one form | Checks or value changes the rule builder cannot express |
| API endpoint | On the server, at `GET`/`POST /api/v1/ext-api/{key}` | Read-only data for a client extension or an outside system |
| Client validator | In the browser, before a form is sent | Instant checks across fields |
| Form script | In the browser, when values change | Setting editable fields and showing messages as someone types |
| Field component | In the browser, as the input of a text field | A custom input, such as a map picker or a signature pad |

Client extensions only advise: the server validates every save as usual.

## 2. Lifecycle

1. **Write** the code in the editor and save. Saving replaces a version that is not yet approved; after approval, the next save starts a new version.
2. **Test** in the sandbox. A server hook runs against sample values or a real record inside a transaction that is always rolled back. The report shows:
   - changed values;
   - field errors;
   - a refusal, or the error;
   - the log;
   - the time taken.
   Outbound HTTP is not sent in a test; it returns status 599.
3. **Submit** for approval. Only a version whose exact code passed a test can be submitted.
4. **Approve.** A holder of Approve code who is neither the version's author nor the extension's last editor approves it.
5. **Deploy.** Deploying an earlier approved version is the rollback.
6. **Turn off** to stop an extension at once. A deployed extension is never deleted, so its history stays.

Every step is audited. Approval, deployment and rollback are security events.

Versions are folders on disk under `LCF_EXTENSIONS_PATH` (default
`storage/app/extensions`): `{organization}/{key}/v{n}/` with the source, the
build and a manifest. A team can keep them in Git. The build's SHA-256 is
checked every time it is loaded, so a file changed on disk after approval never
runs.

## 3. Server hooks

Write one class named `Hook` that implements `ServerHook`. Do not add a
namespace or any `use` lines; the build adds them.

```php
final class Hook implements ServerHook
{
    public function handle(HookContext $ctx): void
    {
        // A visit longer than five days needs a manager's reference.
        $days = (int) $ctx->get('days');
        if ($days > 5 && trim((string) $ctx->get('manager_reference')) === '') {
            $ctx->error('manager_reference', 'Required for visits longer than five days.');
        }
    }
}
```

### Points

| Point | When | What the hook may do |
|---|---|---|
| `onLoad` | A record is opened | Change what is shown, never what is stored |
| `beforeValidate` | Before formulas and validation | Set values |
| `afterValidate` | After validation | Add field errors |
| `beforeSave` | Before writing | Set values; they are validated again |
| `afterSave` | Inside the save, after writing | Log, read, call out; refuse to roll the save back |
| `beforeDelete`, `afterDelete` | Inside a delete | Refuse to stop it |
| `onStatusChange` | Inside every status move, approvals included | Refuse to stop it |
| `onAction` | After an action's work on each record | Refuse to roll it back |

Hooks of one form run in key order at their point.

### `HookContext`

| Method | Gives or does |
|---|---|
| `point()`, `form()`, `record()` | The point, the form key, the record uuid (null for a new record) |
| `user()` | The person saving: uuid, name, roles |
| `get($field)`, `all()`, `old($field)` | Values now and before the change |
| `extra()` | The transition or action, at `onStatusChange` and `onAction` |
| `set($field, $value)` | Changes a value, where the point allows it |
| `error($field, $message)` | Adds a field error |
| `fail($message)` | Refuses the whole operation with this message |
| `log($message)` | Writes to the run's log, shown in the sandbox |
| `records()->list($formKey, $search, $limit)`, `records()->find($formKey, $uuid)` | Reads other forms' records as the person saving: their permissions, record scope and field access apply |
| `http($method, $url, $options)` | Calls out through the egress gateway (allow-list, blocked ranges, 10 s, 1 MB). Options: `headers`, `json`, `body`, `query` |
| `isTest()` | True in the sandbox |

### Rules the code must follow

The build refuses code that uses:
- functions outside an allow-list (strings, arrays, maths, dates, JSON, regular expressions);
- classes other than the SDK's and a few value classes;
- `eval`, `include`, `exit`, globals, superglobals or variable variables;
- callbacks given by name (write an inline closure instead).

At run time:
- Every statement counts against the time limit.
- Any error other than `fail()`, and the time limit itself, refuse the operation with a general message and roll everything back.
- The failure appears in Error Monitoring and in the Operations Center under "Developer extension".

## 4. API endpoints

Write one class named `Endpoint` that implements `ApiEndpoint`. Choose the
permission a caller must hold when you create it.

```php
final class Endpoint implements ApiEndpoint
{
    public function handle(ApiRequest $request): array
    {
        $rows = $request->records()->list('visit_requests', (string) $request->query('q'), 10);

        return ['count' => count($rows), 'items' => $rows];
    }
}
```

- `ApiRequest` offers `method()`, `query($key)`, `input($key)`, `body()`, `user()`, `records()`, `http()`, `log()`, `fail()` and `isTest()`.
- The endpoint runs in a transaction that is always rolled back, so it can read but never write.

## 5. Client extensions

Client code is TypeScript, compiled by esbuild when you save. It runs in a
sandboxed frame with an opaque origin: no cookies, no session, no network.
It talks to the form only through the functions below. `vue` is the only
module it may import (render functions only).

```ts
// Client validator: return { field_key: 'message' } for each problem.
lcf.validator((values) => (values.end_date < values.start_date ? { end_date: 'The end is before the start.' } : {}))

// Form script: set editable fields and show messages when values change.
lcf.script({
  onChange(values, changed) {
    if (!changed.includes('country')) return { set: {}, messages: [] }
    return { set: { phone_prefix: values.country === 'SA' ? '+966' : '' }, messages: [] }
  },
})

// Field component: draw the input of a text field.
lcf.field({
  mount(el, api) {
    // api: value, readonly, locale, dir ('rtl' for Arabic), setValue(v)
    const input = document.createElement('input')
    input.value = api.value ?? ''
    input.dir = api.dir
    input.disabled = api.readonly
    input.addEventListener('input', () => api.setValue(input.value))
    el.appendChild(input)
  },
  update(value) {
    /* the value changed outside the component */
  },
})
```

A field component is attached in the form builder: on a text or long-text
field, set the developer hook property to the extension (only Manage code can
change it). Support both directions: read `api.dir`, and use logical
properties (`margin-inline-start`, not `margin-left`).

## 6. Good practice

- Prefer the builder. A rule, a formula, an action or an automation is easier to review, is translated, and is checked against the shared expression corpus.
- Keep hooks short. They run inside the save of every record of their form, including imports and bulk operations.
- Make `afterSave` and `onAction` hooks safe to run twice. A retried submission replays through its journal and does not run them again, but an action may be re-run.
- Never put secrets in code. Use an integration's credentials (Admin Console → Integrations) and call it through `http()`.
