# ADR-0025: SMTP configured from System Settings

- Status: Accepted (Phase 1)
- Date: 2026-10-02

## Context

The setup wizard and System Settings collect SMTP settings (specification §2).
Credentials must not live in files, and long-running queue workers must pick up
changes without a restart.

## Decision

A custom mail transport (`lcf`, `SettingsSmtpTransport`) reads the `mail`
settings group on every send (the settings cache holds the password encrypted),
rebuilds the Symfony SMTP transport when they change, and sets the configured
sender. TLS is required when "tls" is selected. The setup wizard's test button
sends with the entered values before they are saved.

## Consequences

`MAIL_MAILER=lcf` in production; tests use the array mailer.
