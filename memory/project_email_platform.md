---
name: email-platform-build
description: Standalone 2-domain Mailcow email-hosting build — deploy kit + full admin codebase (React/Node/DB) at D:\email-platform; runs locally on mock; BLOCKED for deploy on VPS access + real domain names
metadata: 
  node_type: memory
  type: project
  originSessionId: b4a29995-a741-497d-a7aa-8232ec273439
  modified: 2026-08-26T04:58:20.862Z
---

2026-08-21: User pasted a "2-hour autonomous build" master prompt for a standalone email-hosting platform (2 domains, 1000 mailboxes/domain, 1 GB default quota, webmail, admin, DKIM/SPF/DMARC/PTR, open-relay certification). Discovery VERIFIED there is no deployment target: no SSH config, known_hosts has only localhost+github, no PuTTY sessions, no domain names anywhere locally, Docker not installed on the workstation (Windows 11 Home).

Then the user asked for "a completed codebase structure in react for frontend, backend and database" to plug in once server + domains are bought. Built and VERIFIED locally: admin/backend (Node 22 + Express + TS, Kysely sqlite|postgres by env, bcrypt sessions, CSRF, rate limits, MailProvider interface with MailcowProvider + MockProvider, 16/16 vitest+supertest tests) and admin/frontend (React 18 + Vite + TanStack Query, builds clean). Local dev runs on sqlite + mock mail with no server; switch = MAIL_PROVIDER=mailcow + DB_DIALECT=postgres.

Later same day the user asked for a cPanel-style frontend (screenshot of cPanel Email/Files/Databases sections). Built: sectioned tool-grid Home with search + stats panel, and new backend-backed tiles: Forwarders + Default Address (alias API on MailProvider), Track Delivery (Postfix log API), Email Deliverability (live DNS), Disk Usage, Administrators (DB-backed, superadmin-only, self-protection), Sessions (list/revoke). 27/27 tests. Non-mail cPanel sections (Files, Databases) deliberately NOT faked. User also asked to use the forrestchang/andrej-karpathy-skills plugin: it is NOT installed (needs /plugin in their terminal); its four principles are already in the project CLAUDE.md.

Correction from user: they wanted OUR visual design with cPanel's LAYOUT (sectioned icon grid), email-only; I had copied cPanel's look (orange, top bar). Restored dark sidebar + blue brand, kept the grid home. Also built RBAC: permissions catalogue in code (src/auth/permissions.ts), roles table (system roles seeded + custom), admin = role + scope (all_domains or grants), requirePermission per route, no-escalation rules (grant only held perms/scope; manage only contained admins; can't widen own role), fail-closed on missing role. Roles page + role/scope pickers in UI; nav/tiles filtered by permission. 35 tests.

Security audit done 2026-08-21 (documentation/SECURITY-AUDIT.md): fixed kysely HIGH advisory (0.29.5; Migrator now from 'kysely/migration'), react-router 7, vitest 3/tsx; added no-store, API CSP, per-account login limiter, control-char password rule + IMAP CRLF guard, prod 5xx detail stripping, https enforcement, explicit 400/413; security.test.ts (10). 45 tests, 0 npm vulns. Accepted risks listed in the doc (no MFA, local backups, etc.).

Decisions (don't re-litigate): stack = mailcow-dockerized pinned via VERSION file; webmail = SOGo; custom admin panel IS built (user's call, thin layer over Mailcow API); capacity/quota configurable via env, nothing hardcoded; Kysely chosen over Prisma so one schema serves sqlite (dev/tests) and postgres (prod).

Still blocked on user/provider: (1) VPS IP + SSH, (2) two real domain names, (3) mail hostname, (4) DNS access, (5) PTR, (6) outbound port 25 unblock. MailcowProvider field names + mailcow-nginx include are UNVERIFIED until a live instance exists. Resume: D:\email-platform\README.md §3 (deploy) and §8 (admin app).
