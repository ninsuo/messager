# Messager

Symfony 8 / PHP 8.4 messaging application using Twilio for SMS and voice calls.

**The app UI is in French.** All user-facing text (templates, labels, messages) must be written in French. Code, comments, variable names, and documentation remain in English.

## Stack

- **Runtime:** PHP 8.4, Symfony 8.0, Doctrine ORM 3.x
- **Database:** MySQL 8.0 (utf8mb4), accessed via `DATABASE_URL` env var
- **Web server:** Caddy (dev: HTTP on :80, prod: HTTPS via Let's Encrypt on www.messager.org)
- **External API:** Twilio SDK 8.x (`twilio/sdk`) — env vars: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `WEBSITE_URL`
- **Frontend:** Asset Mapper (not Webpack), Bootstrap 5.3, Stimulus 3.2, Turbo 7.3

## Docker

```bash
# Dev (http://localhost:88)
docker compose up -d

# Prod (https://www.messager.org)
docker compose -f compose.yaml -f compose.prod.yaml up -d
```

Dev host ports are offset by +10 to avoid collisions (80→90, 3306→3316, 9000→9010). Prod uses standard ports (80, 443).

## Project structure

```
src/
├── Command/          # CLI commands (#[AsCommand])
├── Controller/       # HTTP controllers (#[Route] attributes)
├── Entity/           # Doctrine entities (PHP 8 attributes, NOT annotations)
├── Event/            # Symfony event dispatcher events
├── Form/             # Symfony form types
├── Http/             # Custom HTTP response classes
├── Manager/          # Business logic (Twilio managers)
├── Provider/         # SMS/Call provider interfaces + implementations
│   ├── SMS/          # SmsProvider interface, TwilioSmsProvider, FakeSmsProvider
│   └── Call/         # CallProvider interface, TwilioCallProvider, FakeCallProvider
├── Repository/       # Doctrine repositories
├── Service/          # Service layer (TwilioClient)
└── Twig/             # Twig extensions (custom filters)
```

## Conventions

- **Doctrine mapping:** PHP 8 attributes only (`#[ORM\Column]`), never annotations (`@ORM\Column`)
- **Routing:** PHP 8 attributes (`#[Route]`), never YAML or annotation routing
- **DI:** Autowiring + `#[Autowire(env: 'VAR')]` for env vars — no manual YAML service registration needed
- **Commands:** `#[AsCommand(name: '...')]` attribute, never `setName()` in configure
- **Events:** Must extend `Symfony\Contracts\EventDispatcher\Event`
- **Return types:** Always use `static` (not `self`) for fluent entity setters
- **Naming:** All Twilio-related classes are prefixed with `Twilio` to avoid name collisions. Abstract base classes use the `Abstract` prefix (e.g. `AbstractTwilioController`, `AbstractTwilioEntity`)
- **Symfony Request:** `$request->query->get()` or `$request->request->get()` — never `$request->get()` (removed in Symfony 8)
- **Entity route params:** Doctrine entities are excluded from autowiring in Symfony 8. Use `#[MapEntity(mapping: ['uuid' => 'uuid'])]` (`Symfony\Bridge\Doctrine\Attribute\MapEntity`) on controller action parameters to resolve entities from route parameters by non-id fields

## Provider pattern

SMS and Call sending is abstracted behind interfaces (`SmsProvider`, `CallProvider`). The implementation is swapped per environment via `when@dev` in `config/services.yaml`:

- **prod:** `TwilioSmsProvider` / `TwilioCallProvider` — real Twilio API calls
- **dev:** `FakeSmsProvider` / `FakeCallProvider` — stores to `FakeSms` / `FakeCall` entities, no external calls

Type-hint the interface (e.g. `SmsProvider $sms`) to get the right implementation automatically.

## Twilio integration

Webhook routes are under `/twilio/`:
- `POST /twilio/incoming-call` — incoming voice call
- `POST /twilio/outgoing-call/{uuid}` — outgoing call callback
- `POST /twilio/answering-machine/{uuid}` — answering machine detection
- `POST /twilio/incoming-message` — incoming SMS
- `POST /twilio/message-status/{uuid}` — delivery status callback

All webhook controllers validate Twilio request signatures via `AbstractTwilioController::validateRequestSignature()`.

Events dispatched: see `App\Event\TwilioEvent` constants (e.g. `TwilioEvent::MESSAGE_RECEIVED`, `TwilioEvent::CALL_ESTABLISHED`).

## Verification commands

Run all commands inside Docker to ensure PHP 8.4 (the target runtime):

```bash
docker compose exec php php bin/console lint:container            # DI wiring
docker compose exec php php bin/console debug:router              # Route listing
docker compose exec php php bin/console doctrine:schema:validate  # Entity mappings
docker compose exec php vendor/bin/phpstan analyse src/ --level=6 # Static analysis (level 6)
docker compose exec php vendor/bin/phpunit                        # Test suite
```

## Frontend / Stimulus

Stimulus controllers live in `assets/controllers/` with the `_controller.js` naming convention (e.g. `code_input_controller.js` → `data-controller="code-input"`). Asset Mapper auto-discovers them — no manual registration needed.

CSS styles are in `assets/styles/app.css` using CSS custom properties prefixed `--ms-` (e.g. `--ms-primary`, `--ms-accent`).

## Auth flow

1. `GET /` — login form (phone number input)
2. `POST /auth` — handled by `FirstFactorTriggerAuthenticator`, sends SMS code, redirects to `/verify/{secret}`
3. `GET /verify/{secret}` — 6-digit code input (Stimulus-enhanced individual digit boxes synced to hidden form field)
4. `POST /verify/{secret}` — handled by `FirstFactorVerifyAuthenticator`, authenticates user

## Admin section

Routes under `/admin` are protected by `ROLE_ADMIN` (both `access_control` in `security.yaml` and `#[IsGranted('ROLE_ADMIN')]` on `AdminController`).

- `GET /admin` — dashboard
- `GET /admin/users` — user list with masked phone numbers (`mask_phone` Twig filter)
- `POST /admin/users/create` — create user
- `POST /admin/users/{uuid}/grant-admin` — promote to admin
- `POST /admin/users/{uuid}/revoke-admin` — demote (cannot self-demote)
- `POST /admin/users/{uuid}/delete` — delete user (cannot delete self or admins)

## Twilio SDK quirks

The Twilio PHP SDK uses magic methods (`__call`, `__get`). PHPStan cannot resolve `$client->calls(...)` or `$client->messages(...)`. Use `@phpstan-ignore method.notFound` for these calls.
