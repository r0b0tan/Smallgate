# Smallgate

A very small, self-hosted client portal. Your clients sign in and see their own
projects and the website previews that belong to them. Nothing else.

Built for web agencies, freelancers and in-house teams who keep clients in the
loop by email and just need one honest place to answer *"can I see it?"* —
without handing a third-party SaaS the client list. It is free software under
the MIT licence: use it, change it, run it for your own clients.

**Deliberately not included:** no CRM, no invoicing, no document archive, no
chat, no notification centre. Invoices, documents and discussions stay in email,
where they already work.

## Screens in one paragraph

An administrator creates customers, projects and previews, and invites the
people who may see them. A customer signs in, lands on their project, and clicks
a preview open in a new tab. There is no public sign-up: accounts exist only
because somebody was invited.

## Stack

| Part | Choice |
|---|---|
| Framework | Laravel 13 |
| PHP | 8.4 (in the container) |
| Database | PostgreSQL 17 |
| Frontend | Blade + Tailwind CSS 4, ~20 lines of own JavaScript |
| Tests | Pest 5 / PHPUnit 13 against real PostgreSQL |
| Development | Docker Compose, Mailpit for mail |

A Laravel monolith. No REST API, no SPA framework, no Redis, no microservices,
no external services, no CDNs.

## Requirements

Docker with Compose. PHP, Composer and Node are **not** needed on the host —
everything runs in the container.

For production you additionally need a domain, a TLS-terminating reverse proxy
and an SMTP server that can send invitation and password-reset mail.

## Setup

```bash
git clone https://github.com/r0b0tan/Smallgate.git
cd Smallgate

cp .env.example .env

# Build the image with the host uid/gid so file permissions line up
docker compose build --build-arg UID=$(id -u) --build-arg GID=$(id -g) app

./sg composer install
./sg artisan key:generate
./sg up

./sg artisan migrate
./sg artisan db:seed          # demo data, refuses to run in production

./sg npm install
./sg npm run build
```

Then:

| Service | Address |
|---|---|
| Portal | http://localhost:8080 |
| Mailpit (outgoing mail) | http://localhost:8025 |
| PostgreSQL | localhost:55432 |

For frontend work with hot reload:

```bash
docker compose --profile dev up vite
```

### Demo accounts

The seeder creates a handful of fictional customers so the portal is not empty
on first run. The password comes from `SEED_PASSWORD` and defaults to
`passwort-nur-fuer-lokale-entwicklung`.

| Email | Role |
|---|---|
| `admin@example.test` | Administrator |
| `marion@holzmann.test` | Customer — Holzmann Bau GmbH |
| `sabine@bergblick.test` | Customer — Hotel Bergblick |
| `joerg@altmann.test` | User of a **deactivated** customer (cannot sign in) |

The seeder refuses to run when `APP_ENV=production`. Adapt
`database/seeders/DatabaseSeeder.php` to your own examples, or skip the seed
step and create the first administrator by hand. There is no artisan command for
it, and `role`, `is_active` and `customer_id` are not mass assignable on
purpose, so assign them explicitly in `./sg artisan tinker`:

```php
$user = new App\Models\User;
$user->name = 'Admin';
$user->email = 'you@example.com';
$user->password = 'a-long-passphrase';   // hashed by the model cast
$user->role = App\Enums\UserRole::Admin;
$user->customer_id = null;               // an administrator never has one
$user->is_active = true;
$user->email_verified_at = now();
$user->save();
```

Every further account is created through the invitation flow in the portal.

### Language

The user interface, validation messages and legal pages are **German**. Code,
comments and tests are English. Translating the UI means going through
`resources/views` and `lang/` — there is no locale switcher, and the strings are
not yet extracted into translation files.

## The `./sg` helper

A thin wrapper around `docker compose` so everything runs with the host's
uid/gid:

```bash
./sg up                  # start the stack
./sg down                # stop the stack
./sg artisan <command>   # artisan
./sg composer <command>  # composer
./sg test                # the full test suite
./sg pint                # format code
./sg npm <command>       # npm
./sg shell               # shell in the app container
./sg logs                # follow logs
```

## Roles

**Administrator** — creates and edits customers, projects and previews, invites
client users, resends invitations, blocks accounts, sees everything.

**Customer** — signs in, changes their own password, sees only their own
projects and previews. No write access of any kind.

There is **no public registration**. Accounts come into existence only through
an administrator's invitation.

## Security

The decisions, so you can judge them rather than trust them:

**Passwords** — Argon2id (OWASP parameters: 64 MiB, 4 iterations), exclusively
through Laravel's `Hash` facade. No home-grown cryptography, no encryption of
passwords.

**Sign-in** — rate limiting per email+IP combination (five attempts per minute).
That is *one* counter per pair, not a separate limit per email and per IP:
attacks on one account do not lock another out, but distributed guessing across
many source addresses is not prevented. The session id is regenerated after
login, and there is a single generic error message for wrong password, unknown
address, blocked account and deactivated customer. "Forgot password" answers
identically whether or not the address exists.

**Host header and generated links** — password-reset and invitation mails
contain absolute URLs. So a forged `Host` header cannot send a valid token to a
foreign domain, two layers apply: requests with an unconfigured `Host` are
rejected (`TRUSTED_HOSTS`, outside `local`; subdomains are *not* trusted), and
every generated URL is pinned to `APP_URL` — including in queue workers and
console commands, where there is no request at all. `X-Forwarded-*` is honoured
only from the proxies listed in `TRUSTED_PROXIES`; with none listed the headers
are ignored.

The web server in front should reject unknown hosts itself as well (in nginx, a
`default_server` with `return 444`). The bundled nginx configuration is a
development configuration and deliberately does not.

**Sessions** — database driver, `HttpOnly`, `SameSite=lax`, `Secure` in
production. After a password change or reset, all other sessions are deleted and
remember-me tokens discarded. A blocked user or a deactivated customer loses
access on the **next request**, not at the next login.

**Authorisation** — policies without a blanket `Gate::before`; every ability is
spelled out separately, and the default is deny. Customer data is narrowed
through the `Project::visibleTo()` scope, so a foreign or unknown id yields
**404**, never 403, and is indistinguishable from the outside.

**Mass assignment** — `role`, `customer_id`, `is_active`, `project_id`,
`provisioned_at` and a preview's `status` are `$fillable` nowhere. The
invitation model is fully `#[Guarded]`. PostgreSQL CHECK constraints additionally
enforce that a customer user always has a customer and an administrator never
does.

**Invitations** — 256-bit CSPRNG tokens, stored only as a SHA-256 hash. Time
limited, single use; resending invalidates the previous link immediately.
Redemption runs in a transaction with `lockForUpdate`, so two simultaneous
redemptions cannot both create an account.

**Ids** — ULIDs as primary keys for every publicly visible resource. Sequential
integers would be countable and enumerable.

**Privacy** — technically necessary cookies only. No tracking, no analytics, no
external JavaScript, no CDNs. Fonts are bundled locally from npm packages. The
portal is excluded from search engines with `noindex`. No tokens, secrets or
personal data are written to the log.

**Preview targets** — paths and upstream URLs come exclusively from an allowlist
in `config/previews.php`. Path traversal is resolved lexically; existing paths
are additionally checked against symlinks with `realpath()`. Upstream URLs must
be HTTPS and use an allow-listed host with no IP literal, no credentials and no
unexpected port. Customers never see a target and can never influence one.

These checks are input validation, not complete SSRF defence: hostnames are not
resolved, private or loopback addresses behind an allowed name are not detected,
and redirects and DNS rebinding are not handled. Likewise `realpath()` does not
protect against symlinks created after the check (TOCTOU), nor against targets
that do not exist yet. As long as only `NullPreviewProvisioner` exists, no
connection is opened and no file is served — before real serving lands, both
checks must be repeated and completed at the actual I/O point.

## Previews

In the MVP a preview is **only a protected entry in the portal**. There is no
subdomain serving and no proxy yet.

What is already prepared:

- A single wildcard DNS record (`*.preview.example.com`, configured through
  `PREVIEW_BASE_DOMAIN`) is enough — Smallgate never creates DNS records.
- `previews.hostname` is globally unique, so a `Host` header can later be mapped
  to exactly one preview.
- The `App\Contracts\PreviewProvisioner` interface marks the system boundary.
- The only implementation, `NullPreviewProvisioner`, changes **no** server files
  and runs **no** privileged commands.

The architecture decision for real serving is deliberately still open —
including the security problems of session cookies across several subdomains:
[docs/adr/0001-preview-subdomain-architecture.md](docs/adr/0001-preview-subdomain-architecture.md).

An administrator creates a preview as a draft and releases it with
**Bereitstellen**; the status is the result of that action, never a form field.

## Tests

```bash
./sg npm run build   # needed once: the views embed the Vite manifest
./sg test
```

Tests run against a real PostgreSQL database (`smallgate_test`, created
automatically by the `db` container on first start) and **not** against SQLite —
the schema's CHECK constraints and regex operators are explicitly part of what
is being tested.

Covered among other things: no public registration, creating customers, the
invitation flow including single use and expiry, tenant isolation, 404 instead
of 403 for foreign ids, blocked users and deactivated customers, login rate
limiting, session revocation on password change, mass-assignment protection, and
path-traversal and SSRF defence for preview targets.

## Configuration

Every sensitive value comes from an environment variable. `.env.example`
contains no real secrets and is commented throughout.

Set these before running in production:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=<php artisan key:generate>
APP_URL=https://portal.example.com
SESSION_SECURE_COOKIE=true
LOG_LEVEL=info

# Only needed if the portal is reachable under further names.
TRUSTED_HOSTS=
# Addresses or CIDR ranges of the reverse proxy that terminates TLS.
TRUSTED_PROXIES=
```

`APP_URL` is not cosmetic: it is the canonical base for every generated link and
at the same time the first trusted host. Without `TRUSTED_PROXIES`, an
application behind a TLS-terminating proxy sees `http` instead of `https`.

`SESSION_DOMAIN` stays empty. The session cookie must never be set on the parent
domain — the reasoning is in ADR 0001.

Imprint and privacy policy are placeholder pages configured through `LEGAL_*`.
They are written for German law (§ 5 DDG, GDPR); if you operate elsewhere,
replace the wording in `resources/views/legal/`. Either way the final text needs
legal review before you go live.

## Project structure

```
app/
├── Contracts/          PreviewProvisioner -- the only real system boundary
├── Enums/              UserRole, ProjectStatus, PreviewStatus, PreviewTargetType
├── Http/
│   ├── Controllers/    Auth, Admin, Portal, Profile, Legal
│   ├── Middleware/     EnsureUserIsAdmin, EnsureAccountIsActive
│   └── Requests/       server-side validation
├── Models/             User, Customer, Project, Preview, Invitation
├── Notifications/      invitation, password reset
├── Policies/           explicit, without a blanket Gate::before
├── Rules/              PreviewHostname, AllowedPreviewTarget
└── Services/
    ├── InvitationService.php
    └── Previews/       NullPreviewProvisioner, PreviewTargetGuard
docker/                 PHP image, nginx, PostgreSQL init
docs/adr/               architecture decision records
```

No repository pattern over Eloquent. No interfaces except at an actual system
boundary. No anticipatory multi-tenancy platform.

### A note on customer assignment

A user belongs to exactly one customer — modelled as `users.customer_id`. Every
visibility check goes through `User::accessibleCustomerIds()`. Supporting
multiple assignments later therefore takes a schema change and an edit to **that
one method**, not a rewrite of every query.

## Contributing

Issues and pull requests are welcome. Two things to keep in mind:

- Run `./sg pint && ./sg test` before you push.
- The scope is the point. Features outside the MVP — a CRM, invoicing, file
  storage, a chat — will be declined, however well implemented. `CLAUDE.md`
  records the architecture and security rules the codebase is held to.

## Licence

MIT — see [LICENSE](LICENSE). Use it, change it, self-host it, commercially or
not. It comes without warranty; you are responsible for the deployment you run
and for the personal data your installation processes.
