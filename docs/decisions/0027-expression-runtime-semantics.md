# ADR-0027: Expression runtime details left open by the language specification

- Status: Accepted (Phase 2)
- Date: 2026-10-03

## Context

Implementing the PHP reference evaluator and its TypeScript twin
(`docs/expression-language.md`) surfaced details the specification does not
pin down. Both runtimes must agree byte for byte, so each detail is decided once
here and locked by conformance cases in `docs/conformance/expression-corpus.json`.

## Decision

1. **Exact decimals without extensions.** Neither bcmath nor gmp is assumed.
   Both runtimes implement the same base-10 arbitrary-precision algorithm
   (PHP: digit-string naturals in base-10^7 limbs; TypeScript: `BigInt`).
   Results beyond 34 significant digits round half away from zero in the
   fractional part; more than 34 integer digits is `OVERFLOW`.
2. **Null items in aggregates.** `first`/`last` return the first/last
   non-null item; `distinct` drops nulls; `to_text(list)` and `join` skip nulls.
   (Cases `agg.first.skips_null`, `agg.last.skips_null`,
   `agg.distinct.drops_null`, `to_text.list_skips_null`.)
3. **User attributes.** `@user.attributes.<key>` that the user does not have is
   `null` without a diagnostic: attributes are optional per user, unlike form
   fields (case `ref.user_attribute_absent`).
4. **Row scope.** In a row-form aggregate a bare name resolves to the row's
   field first, then to the outer record; `@row` and `@parent` are only valid
   inside a row-form aggregate (static `TYPE` elsewhere).
5. **Static typing is conservative.** A reference whose type cannot be resolved
   is `any` and re-checked at runtime; definite conflicts (operand types,
   argument types, mixed list items, `if`/`switch`/`coalesce` branches of
   different types, unknown `@user`/`@context` properties, nested row forms,
   `changed()` on a non-reference) are rejected with `TYPE`. An unknown record
   reference is `TYPE` when the form definition is supplied to the checker.
6. **Case mapping.** `upper`/`lower` use Unicode *simple* case mapping
   (one code point to one code point). PHP uses `MB_CASE_UPPER_SIMPLE` /
   `MB_CASE_LOWER_SIMPLE`; TypeScript maps per code point and keeps the
   original when the full mapping is not a single code point, plus a fixed
   table for the code points whose simple and full mappings differ in that
   way (U+1F80–U+1FAF iota-subscript forms, U+1FB3/1FC3/1FF3, U+0130).
7. **Safe regex subset.** Allowed escapes are `\d \w \s \n \t \r` and escaped
   syntax characters; `\-` inside a class. Lazy and possessive quantifiers,
   stacked quantifiers, `{m,n}` with `n > 100` or `m > n`, lookaround,
   backreferences and Unicode properties are `INVALID_ARG`. PHP runs the
   pattern with the `u` and `D` modifiers; TypeScript with the `u` flag.
8. **Umm al-Qura data.** The table for 1300–1600 AH is generated once from
   ICU's `islamic-umalqura` calendar into
   `backend/resources/calendars/umm-al-qura.json`; both runtimes read that file
   so they cannot drift.
9. **Record envelopes.** A record value is `{"t":"record","v":{field: <typed>…}}`
   with optional reserved keys `@title` and `@id` (field keys never start with
   `@`).

## Consequences

Changing any of these requires a new corpus case and a superseding ADR.
