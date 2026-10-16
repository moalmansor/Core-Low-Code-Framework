# ADR-0040: Document templates — Word files, designed templates, and conversion

- Status: Accepted (Phase 4)
- Date: 2026-10-12

## Context

§4.17: upload DOCX templates with placeholders or design HTML templates;
generate DOCX or PDF from any record including repeater tables and linked
data; use them in actions and email attachments. §13 requires untrusted
document input to be parsed without external entities or remote references,
with size and time limits.

## Decision

- **Two kinds, fixed at creation.** A Word template is one `.docx` per
  language (`locale_files`); a designed template is a block design stored in
  a new `design` column (ERD updated) — the email blocks without the button,
  plus a page break — with per-language texts. `html` keeps the design
  compiled per language for reading and search.
- **Placeholders** are the email templates' (expression-language references:
  fields, relation paths, system values). In a Word file a table row whose
  placeholders name a repeater's columns (`{{ lines.quantity }}`) repeats once
  per row. Word splits typed text into runs, so each paragraph is normalized
  first and a placeholder split across runs still fills. Every placeholder is
  checked on save; one the form no longer has is reported on the template.
- **Uploaded Word files are untrusted.** Refused: macros, embedded objects,
  external relationships other than hyperlinks, altChunk imports,
  INCLUDETEXT/INCLUDEPICTURE/LINK/DDE fields, any DOCTYPE, more than 1,000
  entries or 100 MB unpacked. XML is parsed with network access off and no
  entity substitution. Values are inserted as text nodes, never as markup.
- **Outputs.** A designed template becomes Word through our own OOXML writer
  (right-to-left paragraphs, runs and tables for RTL languages) and PDF
  through mPDF (Arabic shaping, as the print view). A Word template becomes
  PDF through LibreOffice Writer, run headless with a throw-away profile per
  conversion and the time limit `documents.convert_timeout_seconds`. The
  container image and CI install it; where it is missing the screen says so
  and PDF cannot be chosen for Word templates — nothing pretends to convert.
  The program path is `LCF_DOCUMENT_CONVERTER` (infrastructure, not a setting).
- **Who sees what.** A document is generated for its readers: a value appears
  only when all of them may see it (the shared `TemplateValues`, ADR-0039).
  A downloaded document belongs to its requester alone and expires after
  `records.job_file_hours`; an email attachment is kept with its email so a
  resend sends the same file.
- **A trailing page break** whose following blocks are all hidden is dropped,
  so conditions never leave a blank last page.
