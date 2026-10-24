# ADR-0066: Usage insight

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.37 asks for usage insight for admins: which forms are used, by whom, how often, where users abandon a form, and which fields are left empty, so that the builder can be improved on evidence. The architecture fixes `usage_metrics` as daily counts:
- columns: day, metric, form, field, user, a hash of those dimensions, and a count;
- metrics: `form_view`, `form_start`, `form_submit`, `form_abandon`, `field_left_empty`, `page_view`, `search`.

It describes the renderer sending batched events to `/api/usage` (§19.23).

It leaves open:
- which events are trusted from the browser;
- privacy;
- retention;
- who reads the figures.

## Decision

**What is counted where.**
- Counted on the server, where they happen and cannot be forged:
  - submissions and the fields left empty in them, when a record is created through the screens or the REST API;
  - opening a custom page;
  - global searches.
- Sent by the screens in batches of at most 50 events (`POST /usage`, at most 60 batches a minute), only for forms the person may open:
  - `form_view`: a form's record list is opened;
  - `form_start`: something is first entered in a new record;
  - `form_abandon`: a started new record is left without submitting, including when the tab closes, using a keep-alive request.

  Events of any other kind, and events for forms the person cannot open, are refused or ignored.
- A field counts as "left empty" when it stores data, the person could see it, and it was empty in the stored record. Fields in repeater rows are not counted.

**Privacy.** System Settings › Adoption › "Usage insight counts":
- "With the names of the people" is the default, because the specification asks "by whom";
- "Without names" stores no user;
- "Do not count" stores nothing.

Counts older than "Keep usage counts for" (395 days by default, 30 to 3,650) are removed every night by `usage:prune`. Only counts are kept, never values or record identifiers.

**Who reads it.** Holders of "Manage forms", who are the people who change forms. The figures appear in Admin Console › Building › Usage insight (`/admin/usage`):
- For a period (7, 30, 90 or 365 days), each form with:
  - views, starts, submissions and abandons;
  - the completion rate (submissions out of starts);
  - the number of people who used it;
  - the day it was last used.
- The forms not used in the period.
- For one form:
  - the fields left empty, as a share of submissions;
  - the people who used it most (unless counting without names);
  - the figures per day.

**Counting.** Each count adds to the day's row for its dimension hash. When two requests both insert the same row, the one that loses retries as an increment. Counting never fails the request it is part of: errors are reported and dropped.

## Consequences

- The browser-reported figures (views, starts, abandons) are indicative. A person who blocks requests is not counted. Submissions and empty fields are exact.
- Turning counting off or to anonymous takes effect at once. Figures already counted stay until they reach the retention limit.
