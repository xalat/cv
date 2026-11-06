# CV API

Public CV reading through REST and GraphQL with API Platform, with admin-only writes.
All stored CVs are public and can be read without authentication.
`User` is not an API resource. Creating, updating and deleting CVs requires a
valid JWT and the `ROLE_ADMIN` role. Ordinary authenticated users can only read.

## Setup

```bash
docker compose --env-file .env.dev up -d --build
docker compose --env-file .env.dev exec php php bin/composer install
docker compose --env-file .env.dev exec php php bin/console doctrine:migrations:migrate --no-interaction
```

These commands use `.env.dev` for Docker Compose substitutions. Use the same
`--env-file` option with your chosen file for subsequent Compose commands.
The database must contain a CV before the collection returns any results.
The development Compose also starts Mailpit and configures PHP to send all
contact mail to it. Its inbox is available at http://127.0.0.1:8025.

The API also offers an HTML representation of individual CVs. There is no
separate frontend or HTML editing interface.

## REST

```bash
curl -H 'Accept: application/json' http://localhost:8000/api/cvs
curl -H 'Accept: application/ld+json' http://localhost:8000/api/cvs/1
curl -H 'Accept: text/html' http://localhost:8000/api/cvs/1
```

For an individual CV, request `Accept: text/html` or open `/api/cvs/1.html`
to display its public content as HTML. JSON and JSON-LD remain available;
the collection and write operations do not offer HTML output.

The public fields are `id`, `name`, `title`, `locale`, `location`, `skills`, `languages`,
`education`, `github`, `linkedin`, `portfolio`, `experiences` and `createdAt`.
Documentation is available at `/api/docs`.

`name` and `title` represent the name and professional title. `location` is a
region or mobility area, not a street address. `languages` and `education` are
optional lists of non-empty strings, defaulting to empty lists. Professional
links are optional HTTP(S) URLs (maximum 2048 characters); set them to `null` to
remove them. Null fields are omitted from REST responses.

`locale` accepts `fr` or `en` and defaults to `fr`, including for existing CVs
after migration. It selects the interface language in React and the API's HTML
representation, not an automatic translation of the CV content. Set
`"locale": "en"` on an English CV through POST, PUT or PATCH (or GraphQL).

Example POST body:

```json
{
  "name": "Example",
  "title": "Fullstack developer",
  "locale": "en",
  "location": "Paris region / Remote",
  "skills": ["PHP", "Symfony", "React"],
  "languages": ["French: native", "English: professional"],
  "education": ["Degree in software development"],
  "github": "https://github.com/example",
  "linkedin": "https://www.linkedin.com/in/example",
  "portfolio": "https://example.com",
  "experiences": []
}
```

Apply migrations before using the new fields. Existing CVs retain their content,
with empty language/education lists and null links. All these fields are public.

## Contact

`POST /api/contact` is public and accepts `application/json`:

```json
{
  "name": "Visitor",
  "email": "visitor@example.com",
  "message": "A project to discuss.",
  "website": ""
}
```

The first three fields are required, trimmed and limited to 100, 254 and 5000
characters respectively. `website` is an optional honeypot and must stay empty.
Unknown fields and non-string values are rejected. The payload is limited to
32 KiB. Messages are sent synchronously using Symfony Mailer, as plain text,
with a fixed sender and recipient and the visitor's address in `Reply-To`.
There is no message table, read endpoint, queue or automatic retry.

For production, configure these variables **only in the backend container's
environment**:

```dotenv
MAILER_DSN=smtp://smtp.example.com:587
CONTACT_FROM=site@example.com
CONTACT_TO=recipient@example.com
```

Use the authenticated SMTP DSN supplied by your mail provider and URL-encode
credentials embedded in it. Authorize `CONTACT_FROM` with your provider and
configure SPF/DKIM/DMARC for the sending domain. Never use the visitor's address
as the sender. Do not use `null://null` in production: it discards messages.
Blank sender/recipient settings disable sending with a 503 response.

### Local testing with Mailpit

The development `docker-compose.yml` includes Mailpit on the PHP network.
PHP uses `smtp://mailpit:1025`, with `site@example.test` as the sender and
`contact@example.test` as the recipient. These development environment variables
override Symfony's `.env.local`. No SMTP credentials are required, and messages
are captured locally rather than delivered to real mailboxes. The production
Compose at the repository root is separate and does not use this configuration.

From `cv-api`, start or update the development services:

```bash
docker compose --env-file .env.dev up -d
```

Submit the form at http://127.0.0.1:5173/contact (with the frontend running), then
open http://127.0.0.1:8025 to inspect the message and its `Reply-To` header.
Alternatively, test the endpoint directly:

```bash
curl --fail-with-body http://127.0.0.1:8000/api/contact \
  -H 'Content-Type: application/json' \
  -d '{"name":"Local test","email":"visitor@example.test","message":"Hello from Mailpit.","website":""}'
```

Only the web interface is published, on the loopback address. SMTP port 1025 is
accessible to PHP over the internal Docker network, not published on the host.
Messages are temporary and can be lost when the Mailpit container is recreated.
For Symfony running directly on the host, publish Mailpit's SMTP port with
`127.0.0.1:1025:1025` and set `MAILER_DSN=smtp://127.0.0.1:1025`,
`CONTACT_FROM=site@example.test` and `CONTACT_TO=contact@example.test` in
`.env.local`.

Responses contain a public `message`, never the configured mail addresses:

| Status | Meaning |
|---|---|
| 200 | The mail transport accepted the message; inbox delivery is not guaranteed |
| 400 | Invalid JSON or non-object payload |
| 413 / 415 | Payload too large / unsupported content type |
| 422 | Invalid fields (`errors` keyed by field where applicable) or honeypot rejection |
| 429 | Too many attempts; `Retry-After` gives the delay in seconds |
| 503 | Sending unavailable or SMTP transport failure; no success is reported |

All attempts are limited to five per hour per client IP, including rejected
forms. The limiter stores hashed IP keys temporarily in a filesystem cache and
uses filesystem locks for concurrent requests. Keep `var/cache` writable and
shared by PHP workers on the single-server deployment. Cache replacement or
clearing resets these counters. With multiple app instances, use a shared cache
and lock backend. Configure trusted proxies explicitly if a reverse proxy is
added; do not trust arbitrary client-supplied `X-Forwarded-For` headers.

Message content, visitor addresses and SMTP exception transcripts are not added
to application logs by this endpoint. The frontend explains the purpose of the
data and its retention in the recipient mailbox while the request is processed.
Mailbox cleanup, backups and deletion requests must be handled by the recipient;
the site does not automatically delete mail.

## GraphQL

Send queries to `/api/graphql`. The GraphiQL interface is available at
`/api/graphql/graphiql`.

```graphql
query {
  cvs {
    edges {
      node {
        id
        name
        title
        locale
        location
        skills
        experiences
        createdAt
      }
    }
  }
  cv(id: "/api/cvs/1") {
    name
    title
  }
}
```

GraphQL IDs are resource IRIs such as `/api/cvs/1`.

## Administration (API only)

If JWT keys have not been generated yet:

```bash
docker compose --env-file .env.dev exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

Create an administrator from the command line (the password is requested twice,
hidden, and must contain at least 12 characters):

```bash
docker compose --env-file .env.dev exec php php bin/console app:create-admin admin@example.com
```

Password constraints are defined on `User::plainPassword`, a non-persisted field.
Every account creation or password-change entry point must set this field and
validate the entity with Symfony Validator before hashing the password, then
call `eraseCredentials()`. Existing users without a password change do not need
to supply a plain password. Doctrine does not automatically run this validation.

Existing email addresses are rejected; this command never promotes or overwrites
an existing account. There is no public registration or role-management endpoint.

Authenticate with `POST /api/login`, sending JSON with `email` and `password`.
The response contains a `token`. Send it as `Authorization: Bearer <token>` on
every admin request, including GraphQL requests. Use HTTPS outside local development.

| REST operation | Endpoint |
|---|---|
| Create | `POST /api/cvs` |
| Replace | `PUT /api/cvs/{id}` |
| Partially update | `PATCH /api/cvs/{id}` |
| Delete | `DELETE /api/cvs/{id}` |

POST and PUT accept `application/json` or `application/ld+json`. PATCH uses
`application/merge-patch+json`. For example:

```bash
curl -X POST http://localhost:8000/api/cvs \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"name":"Example","title":"Developer","location":"Paris","skills":["PHP"],"experiences":[]}'
```

`name` and `title` are mandatory and limited to 255 characters. `location` is
optional (255 characters maximum). `skills` and `experiences` are arrays.
`id` and `createdAt` are server-managed and cannot be changed through the API.
All CVs created by an administrator are immediately public.

GraphQL exposes the equivalent protected mutations `createCv`, `updateCv` and
`deleteCv`:

```graphql
mutation {
  createCv(input: {name: "Example", title: "Developer"}) {
    cv { id name title createdAt }
  }
}
```

For `updateCv` and `deleteCv`, supply `id: "/api/cvs/1"` in `input`.
Public GraphQL queries still work without a token; authorization is enforced on
each mutation, not by blocking the shared GraphQL endpoint.
REST writes return 401 without authentication and 403 for a non-admin account.
GraphQL authorization failures are returned in the response's `errors` field.

## Tests

Functional tests use an isolated, in-memory SQLite database, never PostgreSQL.
PHP needs the `pdo_sqlite` extension to run them. JWT tests generate ephemeral
keys in memory and do not use or overwrite development keys.

```bash
docker compose --env-file .env.dev exec php php vendor/bin/phpunit
```
