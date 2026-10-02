# Expression Language Specification (v1)

Status: **Phase 0 deliverable, normative.** Referenced by specification §4.7 and
`docs/architecture.md` §15. Both runtimes — PHP (`backend/app/Expressions`) and
TypeScript (`frontend/src/expressions`) — implement exactly this document. Where
an implementation and this document disagree, the implementation is wrong.

The language is used for **conditions, formulas, default values, validation
rules, placeholders, calculated columns, action/automation step values, badge
counts, and custom record-scope predicates**. It is not PHP or JavaScript and is
never translated into either.

## 1. Design properties

1. **Pure.** No I/O, no assignment, no loops, no recursion, no user-defined
   functions, no access to the filesystem, network, clock (except `today()`/`now()`
   which read the evaluation context), environment, or arbitrary code.
2. **Total.** Every well-formed AST evaluates to a value. Runtime problems
   (division by zero, overflow, type mismatch, missing reference, invalid argument,
   limit exceeded) yield `null` plus a **diagnostic** (§7); they never throw to the
   caller.
3. **Typed.** Values carry a type (§3). There is no implicit coercion except where
   this document says so.
4. **Deterministic across runtimes.** Decimal arithmetic (never binary floating
   point), Unicode code-point string semantics, a fixed calendar implementation,
   and step-count (not wall-clock) bounds make both runtimes return identical
   results for identical inputs. This is proven by the conformance corpus (§10).
5. **Bounded** (§8).
6. **Stored as an AST** (§6). The text syntax (§2) is a convenience for the formula
   editor; the stored artifact is always the JSON AST produced by the **server-side**
   parser. The visual rule builder produces AST nodes directly.

## 2. Text syntax (grammar)

EBNF (ISO 14977 style; `{ }` = zero or more, `[ ]` = optional):

```ebnf
expression      = or_expr ;
or_expr         = and_expr , { "or" , and_expr } ;
and_expr        = not_expr , { "and" , not_expr } ;
not_expr        = "not" , not_expr | comparison ;
comparison      = concat , [ comp_op , concat ] ;               (* non-associative *)
comp_op         = "=" | "!=" | "<" | "<=" | ">" | ">=" ;
concat          = additive , { "&" , additive } ;
additive        = multiplicative , { ( "+" | "-" ) , multiplicative } ;
multiplicative  = unary , { ( "*" | "/" | "%" ) , unary } ;
unary           = "-" , unary | primary ;
primary         = literal | call | reference | list | "(" , expression , ")" ;

call            = identifier , "(" , [ expression , { "," , expression } ] , ")" ;
list            = "[" , [ expression , { "," , expression } ] , "]" ;
reference       = [ scope ] , path ;
scope           = "@" , ( "user" | "record" | "old" | "row" | "parent" | "context" ) , [ "." ] ;
path            = segment , { "." , segment } ;
segment         = identifier ;

literal         = number | string | "true" | "false" | "null"
                | date_lit | datetime_lit | time_lit ;
number          = digit , { digit } , [ "." , digit , { digit } ] ;
string          = '"' , { char - '"' - "\" | escape } , '"' ;
escape          = "\" , ( '"' | "\" | "n" | "t" | "u" , hex , hex , hex , hex ) ;
date_lit        = "d" , '"' , yyyy , "-" , mm , "-" , dd , '"' ;
datetime_lit    = "dt" , '"' , yyyy , "-" , mm , "-" , dd , "T" , hh , ":" , mi , ":" , ss , "Z" , '"' ;
time_lit        = "t" , '"' , hh , ":" , mi , ":" , ss , '"' ;
identifier      = letter , { letter | digit | "_" } ;              (* ASCII; keys are ASCII *)
```

Lexical rules:

- Whitespace (space, tab, CR, LF) separates tokens and is otherwise ignored.
- Keywords `and`, `or`, `not`, `true`, `false`, `null` are case-sensitive and
  reserved; they cannot be field keys (the form builder enforces this).
- An identifier immediately followed by `(` is a function call; otherwise it is a
  reference path (field keys and relation keys of the current form).
- Negative numbers are written with unary minus; `-2` parses as `neg(2)` and is
  constant-folded by the parser into a literal `-2`.
- Comparison operators are **non-associative**: `a < b < c` is a syntax error.
- Operator precedence (high → low): unary `-`; `* / %`; `+ -`; `&`; comparisons;
  `not`; `and`; `or`. All binary operators except comparisons are left-associative.
- `%` is remainder (sign of the dividend), not percent.

Reference scopes:

| Written | Resolves to |
|---|---|
| `amount` / `employee.department.name` | current record field / relation path (§5) |
| `@record.status`, `@record.id`, `@record.created_at`, `@record.created_by`, `@record.number` | record system values |
| `@old.amount` | value before the current edit (update mode); `null` on create |
| `@user.id`, `@user.name`, `@user.email`, `@user.roles` (list of role keys), `@user.department` (department code), `@user.departments` (code of own department and ancestors), `@user.attributes.<key>`, `@user.locale` | current user (the acting user; delegate when acting on behalf) |
| `@context.mode` (`"create"|"edit"|"view"|"print"`), `@context.form`, `@context.locale`, `@context.timezone`, `@context.param.<key>` (URL/runtime parameters) | evaluation context |
| `@row.<field>` | current repeater row (inside row-scoped aggregate arguments; bare names also resolve to the row first there) |
| `@parent.<field>` | the parent record when evaluating inside a repeater row |

## 3. Type system

| Type | Literal / canonical form | Notes |
|---|---|---|
| `null` | `null` | absence of a value |
| `boolean` | `true`, `false` | |
| `number` | decimal string, e.g. `"12.5"`, `"-3"`, `"0.0001"` | arbitrary-precision decimal, max **34 significant digits**; canonical form has no exponent, no leading `+`, no trailing fractional zeros, no trailing `.`, and `-0` is `0` |
| `text` | `"…"` | sequence of Unicode **code points** (NFC-normalized on input); max 65 535 code points |
| `date` | `d"2026-01-31"` | calendar date, proleptic Gregorian, range 0001-01-01…9999-12-31 |
| `datetime` | `dt"2026-01-31T09:30:00Z"` | instant in UTC, second precision |
| `time` | `t"09:30:00"` | time of day |
| `duration` | (from functions) | whole seconds, may be negative; canonical `"P…"` not used — JSON form `{type:"duration", value:"3600"}` |
| `list<T>` | `[1, 2, 3]` | ordered, homogeneous after null removal; max 10 000 items |
| `record` | (from references) | a reference to a record (`form` uuid + `id`); member access via paths only |

Field types map to expression types: text-like → `text`; numeric/currency/
percentage → `number`; checkbox/toggle → `boolean`; date/month/week → `date`;
datetime-local → `datetime`; time → `time`; duration → `duration`; single select →
`text` (option value); multi-select/tags → `list<text>`; lookups → `record` (or
the display value when a path continues past it); user picker → `record` of users;
file fields → `list<text>` (file uuids); map → `text` (`"lat,lng"`); repeater →
`list<record>` (rows).

### 3.1 Static type checking

The server type-checks every AST against the form definition on save: reference
existence, operand types of operators, argument counts and types of functions,
row scope validity, path depth. A condition must have type `boolean`; a formula's
type must be assignable to its field's type. Type errors block saving the metadata.
At runtime, types are re-checked (values may be `null` or come from external data)
and produce diagnostics instead of exceptions.

## 4. Operators

### 4.1 Arithmetic (`+ - * / %`, unary `-`)

| Left | Op | Right | Result |
|---|---|---|---|
| number | `+ - *` | number | number (exact) |
| number | `/` | number | number, rounded to **16 fractional digits**, half away from zero, then canonicalized |
| number | `%` | number | number, remainder with the sign of the dividend (`-7 % 3 = -1`) |
| date | `+ -` | number (integer days) | date |
| date | `-` | date | number (days, may be negative) |
| datetime | `+ -` | duration | datetime |
| datetime | `-` | datetime | duration |
| duration | `+ -` | duration | duration |
| duration | `* /` | number | duration (truncated to whole seconds toward zero) |
| any | any | `null` | `null` (no diagnostic) |

Anything else → `null` + `TYPE_MISMATCH`. Division or remainder by zero →
`null` + `DIV_ZERO`. A result with more than 34 significant digits in its integer
part → `null` + `OVERFLOW`; fractional digits beyond 34 significant digits are
rounded half away from zero. Adding a non-integer number to a date →
`INVALID_ARG`. Dates outside the supported range → `null` + `INVALID_DATE`.

### 4.2 Text concatenation (`&`)

Both operands are converted with `to_text` (§9.6); `null` becomes `""`. Result is
`text`. Exceeding the text length limit → `null` + `LIMIT_EXCEEDED`.

### 4.3 Comparison

- `=` and `!=` are defined for every pair of types. Values of different types are
  **not equal** (no diagnostic), except `number` vs `number` compare numerically
  (`1 = 1.0` is `true`). `null = null` is `true`; `null = x` is `false`.
  Lists are equal when same length and pairwise equal. Text equality is
  code-point equality after NFC (case-sensitive; use `lower()` for
  case-insensitive comparison).
- `< <= > >=` are defined for number/number, text/text (code-point lexicographic
  order), date/date, datetime/datetime, time/time, duration/duration. If either
  side is `null` the result is `null` (no diagnostic). Other type pairs → `null` +
  `TYPE_MISMATCH`.

### 4.4 Logic (`and`, `or`, `not`) — three-valued (Kleene)

| a | b | `a and b` | `a or b` |
|---|---|---|---|
| true | true | true | true |
| true | false | false | true |
| true | null | null | true |
| false | null | false | null |
| null | null | null | null |
| false | false | false | false |

`not null` = `null`. Operands must be boolean or null; otherwise `null` +
`TYPE_MISMATCH`. Evaluation is short-circuit left to right (`false and X`
does not evaluate `X`, so diagnostics from `X` are not produced).

**Truthiness at the boundary:** when a condition's final value is `null` it is
treated as **false** (the effect does not apply; a `block_submit` does not
block; a `require` does not require). Validation rules treat `null` as "not
violated".

## 5. References and relation paths

- A path is resolved hop by hop: a field key of the current record; if that field
  is a relation/lookup, the next segment is a field (or relation) key of the target
  form, and so on. Maximum **4 hops** (`LIMIT_EXCEEDED` beyond, also rejected
  statically).
- A hop through a **to-many** relation (repeater, one-to-many, many-to-many)
  turns the result into a `list` of the following values (flattened across hops,
  order = row order / id order). At most **10 000** collected values.
- Reference to a field the evaluating user cannot see: on the **server** the value
  is resolved with server authority only for server-side evaluation of rules owned
  by the form (validation, formulas, conditions); expressions whose *result is
  shown to the user* (placeholders, calculated columns, derived fields) see the
  field as `null` when the user lacks access. The client never receives hidden
  values, so client-side evaluation of conditions depending on hidden fields is
  marked `runtime = server_only` by the type checker automatically.
- Missing reference (field archived, relation target deleted) → `null` +
  `MISSING_REF`.
- `@old.x` on create → `null` (no diagnostic).

## 6. AST JSON format

Every node is a JSON object with a `"k"` (kind) member. Nodes carry no source
positions in storage (the editor keeps them separately for diagnostics).

| Kind | Shape | Notes |
|---|---|---|
| literal | `{"k":"lit","t":"number","v":"12.5"}` | `t` ∈ `null, boolean, number, text, date, datetime, time`; `v` is a JSON string for number/text/date/datetime/time, a JSON boolean for boolean, absent for null |
| list | `{"k":"list","items":[<node>…]}` | |
| reference | `{"k":"ref","scope":"record","path":["employee","department","name"]}` | `scope` ∈ `record, old, user, context, row, parent`; path segments are **keys**; the stored definition also carries `"ids"`: the uuids of each hop's field/relation, used to survive renames |
| unary | `{"k":"un","op":"neg"|"not","a":<node>}` | |
| binary | `{"k":"bin","op":"+","a":<node>,"b":<node>}` | `op` ∈ `+ - * / % & = != < <= > >= and or` |
| call | `{"k":"call","fn":"round","args":[<node>…]}` | `fn` is a registered function name (§9) |

Example — `if(amount > 1000 and @user.department = "FIN", round(amount * 0.15, 2), 0)`:

```json
{"k":"call","fn":"if","args":[
  {"k":"bin","op":"and",
   "a":{"k":"bin","op":">","a":{"k":"ref","scope":"record","path":["amount"]},"b":{"k":"lit","t":"number","v":"1000"}},
   "b":{"k":"bin","op":"=","a":{"k":"ref","scope":"user","path":["department"]},"b":{"k":"lit","t":"text","v":"FIN"}}},
  {"k":"call","fn":"round","args":[
    {"k":"bin","op":"*","a":{"k":"ref","scope":"record","path":["amount"]},"b":{"k":"lit","t":"number","v":"0.15"}},
    {"k":"lit","t":"number","v":"2"}]},
  {"k":"lit","t":"number","v":"0"}]}
```

A JSON Schema for the AST (`expression-ast.schema.json`) is generated from this
table in Phase 2 and validates every stored AST.

Evaluation result envelope (both runtimes):

```json
{ "value": {"t": "number", "v": "150"}, "diagnostics": [{"code": "DIV_ZERO", "node": "path/to/node"}] }
```

## 7. Diagnostics (error semantics)

| Code | Raised when | Result |
|---|---|---|
| `DIV_ZERO` | `/` or `%` by zero | `null` |
| `OVERFLOW` | number exceeds 34 significant integer digits | `null` |
| `TYPE_MISMATCH` | operator/function receives an unsupported type at runtime | `null` |
| `MISSING_REF` | referenced field/relation does not exist (archived/deleted) | `null` |
| `INVALID_ARG` | argument outside the function's domain (e.g. `sqrt(-1)`, bad regex, non-integer day count, invalid date parts) | `null` |
| `INVALID_DATE` | date arithmetic leaves 0001-01-01…9999-12-31, or Hijri out of table range | `null` |
| `LIMIT_EXCEEDED` | text/list size, path depth, row count, or step budget exceeded | `null` (step budget: the whole evaluation returns `null`) |

Diagnostics propagate: an expression returns the first-produced value semantics
above and **all** diagnostics collected in evaluation order (deduplicated by code
and node). The form runtime shows them as validation messages on the owning field
or rule, using translatable message keys `expression.<code>`.

## 8. Bounds

| Limit | Value | Checked |
|---|---|---|
| AST depth | 32 | statically (save) and at load |
| AST node count | 2 000 | statically |
| Relation path hops | 4 | statically |
| Rows visited by aggregates / to-many paths | 10 000 per evaluation | runtime |
| Evaluation steps (one step per node visit and per aggregated row) | 100 000 | runtime — replaces a wall-clock timeout so both runtimes stop at the same point |
| Text length | 65 535 code points | runtime |
| List length | 10 000 | runtime |
| Regex pattern length | 256 | statically; plus the safe-subset check in §9.3 |

Additionally the server wraps evaluation batches in a 50 ms wall-clock guard as a
defense in depth; it never changes results within the step budget.

## 9. Function library

Notation: `number?` means the argument may be omitted. Unless stated otherwise,
**any `null` argument makes the result `null`** without a diagnostic, and a
wrong-typed argument yields `null` + `TYPE_MISMATCH`.

### 9.1 Conditional & null handling

| Function | Result | Notes |
|---|---|---|
| `if(cond, then, else)` | type of branches | lazy: only the chosen branch is evaluated; `null` cond → `else` |
| `switch(x, v1, r1, v2, r2, …, default?)` | | first `vi = x` (per `=`) wins; no match and no default → `null` |
| `coalesce(a, b, …)` | | first non-null |
| `is_empty(x)` | boolean | true for `null`, `""`, `[]` |
| `is_null(x)` | boolean | true only for `null` |
| `in(x, list)` | boolean | `x` equals any item (per `=`); `null` list → `false` |
| `changed(ref)` | boolean | `ref` ≠ `@old` value (per `=`); `false` on create |
| `changed_from_to(ref, from, to)` | boolean | `@old` value = `from` and new value = `to`; `null` matches `null` |
| `has_role(key)` | boolean | `key` ∈ `@user.roles` |
| `in_department(code, include_descendants?)` | boolean | user's department equals `code` (or is a descendant when `true`) |

### 9.2 Math

| Function | Notes |
|---|---|
| `abs(x)` | |
| `round(x, digits?)` | half **away from zero**; `digits` integer −10…20, default 0 |
| `floor(x)`, `ceil(x)` | to integer |
| `trunc(x, digits?)` | toward zero |
| `mod(a, b)` | same as `a % b` |
| `power(x, n)` | `n` integer 0…64 (else `INVALID_ARG`); exact |
| `sqrt(x)` | 16 fractional digits, half away from zero; `x < 0` → `INVALID_ARG` |
| `min(a, b, …)` / `max(a, b, …)` | variadic numbers/dates/texts of one type; nulls ignored; all null → `null`. With a single **list** argument behaves as the aggregate (§9.5) |
| `clamp(x, lo, hi)` | |
| `sign(x)` | −1, 0, 1 |

### 9.3 Text

All positions are **1-based** and count **Unicode code points**.

| Function | Notes |
|---|---|
| `len(s)` | code points |
| `upper(s)`, `lower(s)` | Unicode simple case mapping, locale-independent |
| `trim(s)` | removes leading/trailing Unicode `White_Space` |
| `left(s, n)`, `right(s, n)` | `n ≥ 0` |
| `mid(s, start, count)` | |
| `contains(s, sub)`, `starts_with(s, p)`, `ends_with(s, p)` | case-sensitive |
| `replace(s, find, with)` | all occurrences, literal (no regex) |
| `concat(a, b, …)` | like `&`: nulls → `""` |
| `split(s, sep)` | → `list<text>`; `sep = ""` → `INVALID_ARG` |
| `pad_left(s, n, ch)` | `ch` exactly one code point |
| `matches(s, pattern)` | regex **safe subset** valid in both PCRE2 and ECMAScript `u` mode: literals, `.`, classes `[...]`, `\d \w \s` (ASCII semantics), anchors `^ $`, groups `( )`, non-capturing `(?: )`, alternation `|`, quantifiers `* + ? {m,n}` (n ≤ 100); **no** backreferences, lookaround, possessive/lazy-specific behaviors, flags, or Unicode properties. Invalid pattern → `INVALID_ARG`. Full-string search semantics like `preg_match`/`RegExp.test` (use anchors for whole-match). |
| `normalize_arabic(s)` | removes tashkeel and tatweel; folds أ إ آ → ا, ى → ي, ة → ه; converts Arabic-Indic and Extended Arabic-Indic digits to ASCII |
| `to_text(x)` | §9.6 |

### 9.4 Date, time & calendars

| Function | Notes |
|---|---|
| `today()` | context date in the context timezone |
| `now()` | context instant (`datetime`) |
| `date(y, m, d)` | invalid parts → `INVALID_ARG` |
| `datetime(date, time)` | combine as UTC |
| `year(d)`, `month(d)`, `day(d)` | Gregorian parts |
| `weekday(d)` | 0 = Sunday … 6 = Saturday |
| `add_days(d, n)`, `add_months(d, n)`, `add_years(d, n)` | months/years clamp to the last day of the target month (`2026-01-31 + 1 month = 2026-02-28`) |
| `diff_days(from, to)` | `to − from` in days |
| `diff_months(from, to)` | whole months from `from` to `to` (truncated toward zero; day-of-month aware) |
| `start_of_month(d)`, `end_of_month(d)` | |
| `seconds(n)`, `minutes(n)`, `hours(n)`, `days(n)` | → `duration` |
| `duration_seconds(dur)` | → number |
| `to_date(x)` | from `datetime` (in context timezone) or ISO `YYYY-MM-DD` text |
| `hijri_year(d)`, `hijri_month(d)`, `hijri_day(d)` | **Umm al-Qura** calendar (table-driven, 1300–1600 AH); outside → `INVALID_DATE` |
| `from_hijri(y, m, d)` | Hijri (Umm al-Qura) → Gregorian `date` |
| `hijri_text(d)` | `"YYYY-MM-DD"` Hijri digits ASCII, zero-padded |
| `is_working_day(d)` | uses the business calendar in the context (form → department → default) |
| `add_working_days(d, n)` | skips non-working days and holidays of that calendar; `n` integer |

### 9.5 Aggregates (lists, repeater rows, to-many paths)

Two call forms:

- **List form**: `sum(list)` — the argument evaluates to a list.
- **Row form**: `sum(rows_ref, expr)` — `rows_ref` is a reference to a repeater or
  to-many relation; `expr` is evaluated **once per row** in row scope (bare names
  resolve to the row's fields first, `@parent` to the outer record). The row form
  is not a loop construct: it cannot nest more than one level and `expr` cannot
  contain another row-form aggregate.

| Function | Empty input | Notes |
|---|---|---|
| `sum(…)` | `0` | nulls ignored; numbers or durations |
| `avg(…)` | `null` | division rule of §4.1 |
| `min(list)` / `max(list)` | `null` | |
| `count(list)` / `count(rows, cond)` | `0` | list form counts non-null items; row form counts rows where `cond` is `true` |
| `first(…)` / `last(…)` | `null` | |
| `join(list, sep)` / `join(rows, expr, sep)` | `""` | values via `to_text`; nulls skipped |
| `distinct(list)` | `[]` | keeps first occurrence order |

### 9.6 Conversion

| Function | Notes |
|---|---|
| `to_number(x)` | from text: optional leading `-`, digits, optional `.` fraction, surrounding whitespace allowed, Arabic-Indic digits accepted; otherwise `INVALID_ARG`. From boolean: `1`/`0`. |
| `to_text(x)` | number → canonical decimal; boolean → `"true"`/`"false"`; date → `YYYY-MM-DD`; datetime → `YYYY-MM-DDTHH:MM:SSZ`; time → `HH:MM:SS`; duration → seconds as decimal; list → items joined with `", "`; record → its title; null → `null` (but `""` inside `&`/`concat`) |
| `to_boolean(x)` | `"true"/"false"` (case-insensitive), `1/0`; otherwise `INVALID_ARG` |

Display formatting (thousand separators, currency symbols, Arabic-Indic digits,
date display patterns) is **not** part of the language — it is applied by the
renderer from field settings. Expressions always compute on canonical values.

## 10. Conformance corpus

Location: [`docs/conformance/expression-corpus.json`](conformance/expression-corpus.json).

Format:

```json
{
  "version": 1,
  "spec": "docs/expression-language.md",
  "defaults": { "context": { "today": "2026-10-02", "now": "2026-10-02T09:00:00Z",
                              "timezone": "UTC", "mode": "edit", "user": { … }, "calendar": { … } } },
  "cases": [
    { "id": "arith.add.int",
      "expr": "1 + 2",
      "ast": { …optional… },
      "record": { "amount": {"t":"number","v":"10"} },
      "old": { … }, "context": { …overrides… },
      "expect": { "value": {"t":"number","v":"3"}, "diagnostics": [] } }
  ]
}
```

- `expr` is parsed by the **PHP parser** (the reference parser) to an AST; the
  TypeScript parser must produce a byte-identical canonical AST (keys sorted)
  — parser parity.
- When `ast` is present, both evaluators also evaluate that AST directly (it is the
  normative form for that case).
- Both evaluators must return exactly `expect.value` (type and canonical value)
  and the diagnostic **codes** in `expect.diagnostics` (order-insensitive).
- Typed record values use the result envelope encoding (`{"t":…, "v":…}`); lists
  are `{"t":"list","v":[…]}`; repeaters are `{"t":"rows","v":[{field: value…}…]}`.
- CI: `backend` Pest suite `tests/Conformance/ExpressionCorpusTest.php` and
  `frontend` Vitest suite `tests/conformance/expressionCorpus.spec.ts` read the
  same file; any failure fails the build (spec §4.7). Adding a function or
  changing semantics requires adding corpus cases in the same pull request.
- Static cases (`"expectStatic": {"error": "…"}`) assert that the parser or the
  type checker rejects an expression at save time. Static error codes:
  `SYNTAX` (grammar), `TYPE` (operand/argument/result type), `UNKNOWN_FUNCTION`,
  `ARITY` (argument count), `PATH_DEPTH` (> 4 hops), `DEPTH` (AST depth > 32 or
  > 2 000 nodes), `PRECISION` (number literal with > 34 significant digits).
- Every Phase 0 case carries its `ast`. These ASTs are normative: the PHP
  reference parser must reproduce them exactly (canonical key order) from `expr`,
  and the TypeScript parser must match the PHP parser.
- The default evaluation context is in `defaults.context` (today 2026-10-02,
  UTC, edit mode, a user in department `FIN-AP` under `FIN` with roles `manager`
  and `employee`, and a Sunday–Thursday business calendar with a holiday on
  2026-10-04); a case's `context` overrides individual keys. Hijri expectations
  were cross-checked against ICU's `islamic-umalqura` calendar.

The Phase 0 corpus covers every operator, every function in §9, null and error
semantics, bounds, Unicode and Arabic text, Gregorian and Hijri dates, and
aggregates over repeater rows. Phase 2 extends it as implementation proceeds; cases
are never deleted, only added (or corrected with a recorded decision).
