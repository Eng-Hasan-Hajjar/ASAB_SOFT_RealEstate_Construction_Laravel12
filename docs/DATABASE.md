# ASAB SOFT Data Model & Tenant Isolation

## Tenant boundary

All operational tables carry `company_id`, populated exclusively by the authenticated server-side session and checked on every read, update, insert, or delete. The platform owner is separated by a dedicated role and routes; company employees cannot access `/platform`. Every foreign key supplied through a form is checked with `Rule::exists(...)->where('company_id', companyId)`.

**Tables**

- companies → users, properties, units, contacts, leases, invoices, payments, maintenance_tickets, contractors, construction_projects, project_tasks, project_expenses, leads, knowledge_entries, assistant_messages, activity_logs.
- properties → units; units → leases; contacts → leases, invoices; leases → invoices; invoices → payments.
- maintenance_tickets → properties, optional units, contacts, users.
- construction_projects → properties/contacts (optional); project_tasks/project_expenses → construction_projects; project_expenses → contractors (optional).

**Finance:** all amounts are `DECIMAL(15,3)` to support BHD's three fractional digits. Each company uses one currency: SAR (Saudi Arabia) or BHD (Bahrain). No currency conversion or multi-country cross-currency accounting is implemented. For operations in two countries, create separate companies.

**Rental state:** Active leases with intersecting dates on one unit are rejected inside a database transaction locked on the unit. Invoices are issued manually; payment totals are capped by the outstanding invoice balance. No automatic billing schedule or official government document verification is implemented.

**Important:** DB-level constraints alone do not enforce cross-table company equality. Controller validation and company-scoped queries do. If the platform later exposes APIs, services must preserve these checks, preferably with shared policies and comprehensive integration tests. High-security production deployments should consider tenant enforcement at the database/data-access layer.
