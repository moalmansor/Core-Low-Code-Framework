# ADR-0064: Help content, knowledge pages and changelogs

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.37 asks for:
- help content per form, per field and per page, translatable, shown as tooltips, side panels or a help page;
- an internal knowledge page per application;
- a changelog that admins publish to users when forms change.

The architecture fixes the `help_content` table (target type and id, display, published, order) and says that knowledge and changelog pages are `pages` of type `knowledge` and `changelog`, with "publish to users" creating an announcement (§19.23). It leaves open:
- who writes help;
- who may read it;
- how admin areas, which have no row, are targeted;
- what a tooltip may contain.

## Decision

**Who writes it.** Holders of "Manage help content" (`system.manage_help_content`) use Admin Console › Building › Help content (`/admin/help`). The editor's pickers come from `GET /help-content/targets`, which is gated by the same permission. A help writer therefore needs no form, page or application permissions to choose what to write about.

**What it can be about.**
- A form, or one of its published fields or groups. Field and group help needs the form to be published, because the help is attached to the published field.
- A page, or an application.
- An admin area of the console. An admin area has no row of its own, so its key is stored as a stable number (CRC-32 of `admin_area:<key>`), and only built areas are accepted.

**How it shows.**
- *Tooltip*: only for a field or a group. Stored as plain text: tags are stripped, at most 500 characters. Shown by an information icon beside the label, after the field's own tooltip.
- *Side panel*: rich text, sanitized when saved and again when served. Shown in full in the Help panel, which opens from a Help button in the top bar. The button appears only where help exists.
- *Help page*: rich text, sanitized the same way. Listed in the Help panel and opened at `/help/{uuid}`.

Title and text are translatable per locale. Unpublished help is never served.

**Who may read it.** Help is served for what the person may open, and only that:
- a form's help (and its fields' and groups') needs the form's view permission and its feature flag;
- a page's help needs the page to be viewable;
- an application's help needs access to the application;
- an admin area's help needs one of the area's permissions.

`GET /help/{uuid}` applies the same check to the help page's subject and answers 404 otherwise, so help never reveals what someone cannot open.

**Concurrency and audit.** Saves carry the hash the editor loaded (409 when it changed). Creating, changing and deleting help is audited (`help.created`, `help.updated`, `help.deleted`).

**Knowledge and changelog pages.**
- Both are page types with written content, edited like content pages.
- A saved, published changelog shows a "Tell users" button. The button needs both "Manage pages and menus" and "Publish announcements".
- The button calls `POST /pages/{uuid}/announce`, which publishes a bell announcement for the page's application. The announcement has the page's title in every language it has, and a link to the page in the same languages. Everyone with access to the application sees it in their notifications.
- Only changelog pages can be announced.

**Related fix.** Rich text with a link inside the application (`/pages/…`) made the HTML sanitizer raise a warning, because its default scheme `http` is not an allowed scheme, and the request failed. The default scheme is now `https`. Relative links are kept, and `http:` links are still removed.

## Consequences

- Help follows permissions: people never learn from help that a form or area exists if they cannot open it.
- Tooltips cannot carry markup, so they cannot carry links or script.
- The client caches help per language for the session. The editor clears that cache after a save, so administrators see their change at once. Other people see it on their next sign-in or reload.
