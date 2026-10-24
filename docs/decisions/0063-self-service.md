# ADR-0063: Self-service preferences, pinned records, shortcuts and digests

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.36 lists what people set for themselves "within limits the admin sets":
- language, time zone, calendar, date and number format;
- theme and density;
- notification channel preferences and digest frequency;
- the default landing page;
- saved views, pinned records and personal shortcuts;
- personal API tokens if the admin permits them.

Phase 1 stored most preferences, but only the theme and language changed what people saw.

## Decision

**Limits** — System Settings › Self-service › "Preferences people may change themselves" lists the preferences left to people; all of them by default.
- The server refuses any other with "Your organization sets this for everyone".
- `/me` returns the editable list, so the profile shows only those preferences and the language switcher hides when the language is fixed.
- Personal API tokens are released or held back with the `personal_api_tokens` capability flag (ADR-0062).

**Preferences take effect everywhere** — every formatter reads one display-preferences module, filled from `/me`:
- time zone, calendar (Hijri, or both), Western or Arabic-Indic digits;
- a date pattern (`dd/MM/yyyy`, `MM/dd/yyyy`, `yyyy-MM-dd`, `dd.MM.yyyy`);
- number separators.

A field's own setting (a fixed time zone, a Hijri date field) still wins. Density sets the control height over the theme's.

**Start page** — home, My Work, an application's home, a form or a page, chosen from what the person can open. It is shown the first time home is opened after signing in.

**Pinned records and shortcuts**
- Stored on `user_preferences` (`pinned_records` up to 50, `shortcuts` up to 20), by form and record uuid, so they survive packages.
- Each request checks them again: a pin to a record the person can no longer see, or that was deleted, is simply not shown, and pinning needs the record to be visible.
- Shown on the home page, with a Pin button on every record.

**Digests**
- Daily or weekly (Mondays) at 07:00 in the person's time zone, sent by an hourly scheduled command.
- A digest lists the unread notifications of the period, escaped, with links into the system.
- Nothing unread means no e-mail.
- A new column, `user_preferences.digest_sent_at`, makes it once per period whenever the scheduler runs.

## Consequences

- An organization can fix, for example, the calendar and time zone for everyone while leaving the theme to people.
- Formatting follows the person consistently. Fields configured with their own zone or calendar keep it.
