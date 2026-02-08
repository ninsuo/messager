# Messager

Symfony 8 / PHP 8.4 messaging application using Twilio for SMS and voice calls.

## Stack

- **Runtime:** PHP 8.4, Symfony 8.0, Doctrine ORM 3.x
- **Database:** MySQL 8.0 (utf8mb4), accessed via `DATABASE_URL` env var
- **Web server:** Caddy (dev: HTTP on :80, prod: HTTPS via Let's Encrypt on www.messager.org)
- **External API:** Twilio SDK 8.x (`twilio/sdk`) — env vars: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `WEBSITE_URL`

## Docker

```bash
# Dev (http://localhost:88)
docker compose up -d

# Prod (https://www.messager.org)
docker compose -f compose.yaml -f compose.prod.yaml up -d
```

Dev host ports are offset by +8 to avoid collisions (80→88, 3306→3314, 9000→9008). Prod uses standard ports (80, 443).

## Project structure

```
src/
├── Command/          # CLI commands (#[AsCommand])
├── Controller/       # HTTP controllers (#[Route] attributes)
├── Entity/           # Doctrine entities (PHP 8 attributes, NOT annotations)
├── Event/            # Symfony event dispatcher events
├── Http/             # Custom HTTP response classes
├── Manager/          # Business logic (Twilio managers)
├── Repository/       # Doctrine repositories
└── Service/          # Service layer (TwilioClient)
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

```bash
php bin/console lint:container            # DI wiring
php bin/console debug:router              # Route listing
php bin/console doctrine:schema:validate  # Entity mappings
vendor/bin/phpstan analyse src/ --level=6 # Static analysis (level 6)
```

## Twilio SDK quirks

The Twilio PHP SDK uses magic methods (`__call`, `__get`). PHPStan cannot resolve `$client->calls(...)` or `$client->messages(...)`. Use `@phpstan-ignore method.notFound` for these calls.
