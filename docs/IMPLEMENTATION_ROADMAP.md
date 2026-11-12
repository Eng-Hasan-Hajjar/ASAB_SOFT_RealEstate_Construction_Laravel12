# Roadmap | Delivery gates

## Included in this package

- Owner console + company creation / suspension.
- Per-company staff and permission groups.
- Properties, units, contacts, leases, invoices/payments, maintenance, construction projects/tasks/expenses, contractors, CRM leads, knowledge entries.
- Arabic responsive interface, local rule-based assistant, dashboards and demo fixtures.

## Needed before production onboarding

1. Install dependencies, configure MySQL, run migrations and the complete automated test suite on a real test environment.
2. Independent security review (tenant isolation, CSRF/session protection, rate limiting, authorization). Add 2FA and verified admin invitations, password reset/email delivery.
3. Production deployment behind HTTPS + backups, queue worker + monitoring + error alerts, restore drill and disaster recovery.
4. Contract workflow approval, invoices/VAT compliance, official Saudi Ejar and Bahrain tenancy/municipal/legal integration requirements, according to legal and accounting professionals. Current documents are internal-only.
5. Saudi PDPL and Bahrain PDPL privacy review, employee access policies, data retention/deletion and data transfer approvals; validate hosting location and processors.
6. Implement real AI model only if approved and contractually allowed, with offline/local hosting or privacy safeguards. Current assistant is deterministic text matching and queries, not an LLM or RAG.
7. Better permission matrices, attachments, audit-event immutable storage, bulk import/export, file virus scanning, notifications, approvals and more end-to-end tests.
8. Full lease schedule billing, reminders, VAT/ZATCA-compliant integration, accounting reconciliation, bank integrations and formal construction change orders.

No claim of compliance, certification, or customer-ready production is made by this prototype.
