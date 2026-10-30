# Administrator guide

This guide is for the people who build and run business systems in the
framework, entirely from the screens. Everything here is done in the browser.
None of it needs code, a configuration file or a deployment. The contract
behind every screen is [`docs/specification.md`](../specification.md).

Everything works in Arabic and in English. Switch the language from the
top bar. Every name, label, message and template is entered once per language
(Admin Console → Translations shows what is missing).

## 1. First steps

1. **Setup wizard.** The person installing the system gets a one-time token (deployment guide §1). The wizard creates the organization and the Super Admin. That person must enrol two-factor sign-in.
2. **System settings** (Admin Console → System settings):
   - languages and the default language;
   - mail (SMTP; send a test message);
   - security: password policy, session lifetime, lockout, API rate limit;
   - records: import and export limits;
   - retention defaults.
3. **People.**
   - **Departments:** the tree that record scopes and reports follow.
   - **Roles & permissions:** what each role may do.
   - **Users:** add people one by one, import them, or connect SSO/LDAP in settings.
   - **Access policies:** per role, the allowed addresses, hours, session limits, and whether two-factor sign-in is required.
4. **Appearance & branding:** logo, colours and the theme per application. The contrast check refuses colours that fail WCAG AA.

Access is decided in tiers: a specific user, then a role, then a department.
Within a tier a deny beats an allow, and a more specific tier overrides a less
specific one. A hard deny cannot be overridden by anything.

## 2. Building a system

### Applications and forms

1. **Applications** groups forms, pages and navigation under one name, with its own menu, home screen and theme.
2. **Forms & collections → New form.** A collection is reference data shared by forms (customers, products). A form is a business record (requests, orders).
3. The **form builder** has three parts:
   - **Fields:** about 60 types: text, numbers, dates, choices, lookups to other forms, files, signatures, formulas and more.
   - **Groups:** sections, tabs, wizard steps, repeating tables and inline sub-forms.
   - **Properties panel:** labels per language, validation, default values, conditions (show, hide, require, make read-only), formulas, help text, and table settings (filterable, sortable, searchable).
   Conditions, formulas and defaults use the expression editor. It builds expressions by picking, never by typing code.
4. **Publish.** The impact preview lists every database change, whether it is online, and the rows affected. An additive change (a new field) is applied without downtime. A destructive one needs confirmation, and a snapshot is taken first so it can be rolled back from the form's versions.

### Workflow and access

- **Workflow** (on the form): statuses, transitions, who may move a record, required fields and comments, approvals (one, all, any n, weighted), and SLA timers with escalations.
- **Access matrix:** which roles may view, create, edit and delete records, per status; field and group visibility per role.
- **Record access rules:** which records each role, department or user sees (own, own department, department tree, assigned, all, or a condition).
- **Justification rules:** which changes need a reason, and from which reason codes.

### Working with records

- **Views:** columns (including fields of linked forms), filters, sort, totals and page size per role; people can save their own views.
- **Record page:** panels (related records, summaries, linked fields), previews of linked records, and print layouts (HTML and PDF).
- **Assignment, queues & delegation:** who gets new work, shared queues, and stand-ins while someone is away.

## 3. Automating

- **Actions:** buttons on a record or a list. They run a chain of steps (set values, move status, create records, send e-mail, call an integration) behind a permission and a confirmation.
- **Notifications:** e-mail templates, in-app notifications, and SMS or messaging channels, sent on events or conditions to people chosen from the record.
- **Document templates:** Word or HTML templates filled from a record, downloaded as DOCX or PDF.
- **Automations:** "when this happens, do that" on record events, conditions or a schedule.
- **Scheduled tasks** list them.
- **Custom downloads:** spreadsheet profiles across related forms (several levels deep), run on demand or on a schedule.
- **Imports:** from spreadsheets, with a column mapping, a preview, a dry run and an error report. A large import runs in the background; you get a notification when it ends.

## 4. Sharing and integrating

- **Pages, home screens and navigation** per application, role or person.
- **Reports & dashboards:** charts and tables over the forms people may see.
- **Global search:** choose which forms and fields are searched, and by whom.
- **External access:**
  - public forms, with CAPTCHA and limits;
  - external users with their own portal;
  - links that expire;
  - signature requests.
- **Integrations:**
  - REST APIs with stored credentials;
  - external data sources, used as option lists or record panels;
  - sync jobs;
  - inbound endpoints;
  - webhooks.
  Every outbound call goes through the egress allow-list in System settings.
- **Configuration packages** move a configuration between environments. The drift report shows the differences.
- **Blueprints** copy a form, a view or a whole application.

## 5. Keeping it healthy

- **Operations Center:**
  - the e-mail queue;
  - failed submissions, jobs, webhooks and integrations, each with a retry;
  - system health;
  - alert rules (queue size, error rate, storage, the largest table, scheduler lag).
- **Error monitoring:** every unexpected error with its reference number. The person who hit it was shown only that number.
- **Audit log:** every change, sign-in, permission change and export. Entries are hash-chained and can never be edited or deleted by anyone, Super Admin included. The chain is verified nightly.
- **Data quality** (on each form):
  - duplicate rules and merging;
  - guarded bulk edits;
  - the data-repair scan;
  - the recycle bin.
- **Usage insight:** which forms, screens and features are used.

## 6. Retention and personal data

Admin Console → **Retention and personal data**:

- **Policies:** for each data class, how long it is kept and what happens then: archive, export then delete, or delete.
  - Each run is listed with what it did.
  - Archived records can be found and restored under their original id.
  - The audit log is only ever archived, never deleted. Its archive carries a proof that the chain is intact.
- **Legal holds:** on one record or on a condition. A held record is never deleted, archived or anonymized, by anyone or anything, until the hold is released.
- **Personal data requests:** locate everything about a person, then export it, anonymize it, or delete it.
  - Each step needs a reviewer, a justification and a fresh two-factor check.
  - Anonymizing shreds the person's values from the audit trail without breaking the hash chain.
- **Storage:** use by form and by person, and quotas enforced at upload.

## 7. Help for the people using the system

- **Help content:** help on a form, a field or a page, shown as a tooltip, a side panel or a help page.
- **Guided tours and first-use hints.**
- **Announcements** to everyone, an application or a role.
- **Maintenance mode** for the whole system or one application, with a message.

## 8. When code is needed

The rare cases the builder cannot express are handled by developer extensions,
written and approved by two different people. See the
[developer extension guide](developer-extensions.md).
