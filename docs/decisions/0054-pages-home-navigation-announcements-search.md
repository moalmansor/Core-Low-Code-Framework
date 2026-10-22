# ADR-0054: Pages, home screens, navigation, announcements, maintenance mode and global search

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.13 and §4.29 ask for:

- custom pages (rich content, links, dashboards, embedded reports, a form shown directly), placed in the menu with their own permissions;
- a home screen per role, department or person, built from widgets;
- a navigation builder with unlimited depth, icons, live badges, separators, section headers and visibility per role, department or condition;
- global search configuration: searchable forms and fields, weights, result display, per-role scope;
- announcements and banners: system-wide or per application, scheduled, dismissible, targeted by role, with severity;
- maintenance mode for the whole system or one application, with admins keeping their access.

None of these may widen what a person can open: every list, count and search still goes through the record scope (§16.6) and field access (§4.11).

## Decision

**Pages**
- A page belongs to an application and is content, a link, a dashboard, a report, or a form's records list (`form_host`).
- Its name and content are translatable. Content is sanitized with the same allow-list as other rich text, both when saved and when shown, because translations can also be edited in the translations manager.
- A link is an `https://` address or a path inside the application; `javascript:` and protocol-relative addresses are refused.
- Opening a page needs `page.{uuid}.view`, an active application and, for a dashboard, report or form, that object's own permission as well. A page never grants access to what it hosts.
- Deleting a page is a soft delete with an audit entry; its menu items and its permission go with it.

**Navigation**
- Menu items now open forms, collections, pages, reports, dashboards or My Work, besides links, section headers and separators.
- Nesting goes to 20 levels. The specification says "unlimited"; the bound only keeps a tree from becoming pathological (500 items, 20 levels).
- An item can show a live badge: my open work items, my pending approvals, my overdue items, or the records of its form I can see. Counts are worked out per person through My Work's own filtering and the record scope, capped at 999+, and cached for 30 seconds per person and access epoch. They refresh every minute while the window is visible.
- Visibility per role, department or condition is one rule in the expression language on the person (`@user`, `has_role`, `in_department`, attributes, today). It narrows who sees the item; the item's target permission still decides whether it can be opened. A rule referring to a record field is refused when saved. A rule that has gone missing hides the item.
- Links and items without a target keep the earlier behaviour: they need the item's own `menu.{uuid}.view`.

**Home screens**
- A screen is for everyone, a role, a department or one person, either the main home (`/`) or an application's own home (`/a/{application}`, linked from the application's section in the sidebar).
- The screen a person gets follows the access precedence: their own, then one of their roles', then their department's (including parent departments), then the default. Within a tier the highest priority wins.
- Widgets: my work, my records, a report (charts and key figures are reports), shortcuts to a form's list or new record, pinned links, my recent changes, announcements, a form's latest records, and content.
- Each widget can carry a rule on the person. Report and form widgets are shown only with that object's permission, and shortcuts only where the person may view or create.
- Widgets load their data through the ordinary endpoints. The records list gains a `mine` filter, which only narrows. Recent activity lists the person's own changes to records they can still see.
- Record titles in recent activity and in My Work are built only from fields the person may see. My Work previously built them without field access; this is fixed here.

**Announcements**
- Title and body are translatable; the body is sanitized.
- Placement: a banner across the top of every page, home screens with an announcements widget, or the notification bell.
- An announcement is shown between its start and optional end, system-wide or for one application the person may use, to everyone or to some roles.
- Dismissible ones stay dismissed per person. Publishing needs "Publish announcements".

**Maintenance mode**
- Per application (Phase 2): records answer 423, and pages and application home screens refuse with the application's message.
- System-wide, as the architecture describes: the setting group `maintenance` (on, optional end) and a translatable message on the platform organization.
  - Signed-in and API-token requests answer 503 with code `maintenance`, except for people who may switch maintenance mode.
  - "Who am I", signing out and the maintenance status stay open, so the interface can show a maintenance page.
  - The sign-in page shows the message. People who keep working see a banner.
- It is switched from a dialog on the Applications screen and audited.

**Global search**
- Per form: on or off, up to 10 text fields with a weight of 1–10, a field shown under each result, and the roles that may search it (empty means everyone who may view the form).
- Sensitive and encrypted fields cannot be chosen.
- A search needs at least two characters. It looks only in forms the person may view, only in fields they may see, and only in their record scope, with a case- and accent-insensitive match. It returns up to 5 records per form and 30 in all.
- Results are ranked by the weights: an exact value counts three times and a value that starts with the term twice.

## Consequences

- Admin areas "Home screens", "Global search" and "Announcements" join the console. Pages are reached from an application's row menu.
- The header gains a search box; the shell gains a banner strip; the bell shows bell-placed announcements above notifications.
- The `maintenance` settings group is edited by its own endpoint and dialog, not by the generic settings screen.
- e2e: the screen sweep waits out the rate-limit minute it used after restoring the limit, so the next spec's `/me` is not refused with 429. That refusal had been read by the interface as "signed out", which made the next sign-in fail on SQL Server.
