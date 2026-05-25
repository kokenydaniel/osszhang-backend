# Összhang Backend

REST API for **Összhang** — a family money app.  
It stores households, users, budgets, bills, debts, savings, meters, and business orders.

This repo is the **backend**. The web app lives in the [frontend repo](https://github.com/kokenydaniel/osszhang-frontend).

---

## Live API

| | URL |
|---|---|
| **Base URL** | `https://osszhang-backend.fly.dev/api` |

Hosted on [Fly.io](https://fly.io). Database: PostgreSQL.

---

## Demo data

Every deploy runs migrations and seeds a **demo household**.  
Safe to run again — it skips if the demo already exists.

### Demo login

| Username | Password | Role |
|----------|----------|------|
| `demo` | `demo1234` | Admin |
| `viki` | `demo1234` | Member |

- Household name: **Összhang Demo**
- Both users have sample data in all modules

### Seed commands (local or SSH)

```bash
# Create demo if missing
php artisan demo:seed

# Delete old demo and create again
php artisan demo:seed --fresh
```

---

## Users and households

- **Register** (`POST /api/register`) — creates a new household and the first **admin** user.
- **Login** (`POST /api/login`) — username and password.
- **Add member** (`POST /api/household/members`) — **admin only**. Admin sets username, password, role, and permissions.
- **Update / remove member** — admin only.
- Members do **not** join with an invite code. Only the admin creates accounts.

---

## What the API does

| Module | Endpoints (examples) |
|--------|----------------------|
| **Auth** | `POST /login`, `POST /register`, `GET /me` |
| **Household** | settings, members, categories |
| **Budget** | `transactions` — income and expenses |
| **Utilities** | bills, monthly settlement |
| **Debts** | loans and payments |
| **Savings** | goals and ledger entries |
| **Investments** | investment records |
| **Meters** | meters and readings |
| **Business** | orders (+ optional Shopify import) |
| **AI** | budget tips, categorization (needs OpenAI key) |

All protected routes need a **Sanctum Bearer token**:

```http
Authorization: Bearer YOUR_TOKEN
```

---

## Tech stack

| Tool | Version |
|------|---------|
| [Laravel](https://laravel.com) | 11 |
| [PHP](https://www.php.net) | 8.3+ |
| [Laravel Sanctum](https://laravel.com/docs/sanctum) | API tokens |
| PostgreSQL | production |
| SQLite | local (default in `.env.example`) |

Optional: OpenAI (AI features), Shopify (order import).

Sensitive household data can be encrypted at rest (see `HouseholdCipherService`).

---

## Requirements

- **PHP** 8.2 or newer (8.3 recommended)
- **Composer**
- **PostgreSQL** or **SQLite**
- **Node.js** — only if you run the full Laravel dev script with Vite

---

## Run on your computer

### 1. Install PHP packages

```bash
composer install
```

### 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

Default database is **SQLite** (`database/database.sqlite`).

For PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=osszhang
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### 3. Database

```bash
touch database/database.sqlite   # only for SQLite
php artisan migrate
php artisan demo:seed
```

### 4. Start the server

```bash
php artisan serve
```

API base URL: [http://localhost:8000/api](http://localhost:8000/api)

### 5. Test login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"username":"demo","password":"demo1234"}'
```

You should get `access_token` and `user` in the JSON response.

### 6. Add a member (admin token)

```bash
curl -X POST http://localhost:8000/api/household/members \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "username": "ildi",
    "password": "temp1234",
    "first_name": "Ildi",
    "last_name": "Demo",
    "role": "member",
    "permissions": ["budget", "utilities"]
  }'
```

---

## Environment variables

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_KEY` | Yes | App encryption key (`php artisan key:generate`) |
| `APP_URL` | Yes | Public URL of the API |
| `DB_*` | Yes | Database connection |
| `OPENAI_API_KEY` | No | AI features |
| `SHOPIFY_STORE_URL` | No | Shopify import |
| `SHOPIFY_ACCESS_TOKEN` | No | Shopify import |

| `FRONTEND_URL` | Yes (billing) | Next.js app URL for Stripe redirects (e.g. `https://osszhang.vercel.app`) |
| `STRIPE_KEY` | Yes (billing) | Stripe publishable key (`pk_live_...` or `pk_test_...`) |
| `STRIPE_SECRET` | Yes (billing) | Stripe secret key |
| `STRIPE_WEBHOOK_SECRET` | Yes (billing) | Webhook signing secret from Stripe Dashboard |
| `CASHIER_CURRENCY` | Yes (billing) | e.g. `HUF` |
| `STRIPE_PRICE_*` | Yes (billing) | Stripe Price IDs for Pro/Premium monthly/yearly plans |

On Fly.io, set secrets with `fly secrets set KEY=value`.

### Stripe in production

1. Create **live** products/prices in Stripe (or reuse test prices while testing).
2. Set Fly secrets:

```bash
fly secrets set \
  APP_URL=https://osszhang-backend.fly.dev \
  FRONTEND_URL=https://osszhang.vercel.app \
  STRIPE_KEY=pk_live_... \
  STRIPE_SECRET=sk_live_... \
  STRIPE_WEBHOOK_SECRET=whsec_... \
  CASHIER_CURRENCY=HUF \
  STRIPE_PRICE_PRO_MONTHLY=price_... \
  STRIPE_PRICE_PRO_YEARLY=price_... \
  STRIPE_PRICE_PREMIUM_MONTHLY=price_... \
  STRIPE_PRICE_PREMIUM_YEARLY=price_...
```

3. In [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks), add endpoint:

   `https://osszhang-backend.fly.dev/stripe/webhook`

   Events: `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_succeeded`.

4. Deploy — migrations create Cashier tables automatically.

**Beta mode:** Super admins can enable **Platform → Beta version** in Settings. While beta mode is on, tier restrictions and Stripe checkout are disabled; all features stay usable without payment. Turn beta off to enforce live subscriptions.

---

## Useful commands

| Command | What it does |
|---------|--------------|
| `php artisan serve` | Start local server (port 8000) |
| `php artisan migrate` | Run database migrations |
| `php artisan demo:seed` | Create demo household |
| `php artisan demo:seed --fresh` | Reset demo household |
| `php artisan test` | Run tests |
| `composer run dev` | Server + queue + logs (full dev stack) |

---

## Project folders

```
app/
├── Http/Controllers/Api/   # API endpoints
├── Services/               # Business logic
├── Models/                 # Database models
└── Console/Commands/       # Artisan commands

routes/api.php              # All API routes
database/migrations/        # Database schema
database/seeders/           # DemoHouseholdSeeder
docker/                     # Nginx + start script for Docker/Fly
fly.toml                    # Fly.io config
```

---

## Deploy on Fly.io

Region: **fra** (Frankfurt). Config is in `fly.toml`.

```bash
fly deploy
```

### Release command (automatic)

On each deploy, Fly runs:

```bash
php artisan migrate --force && php artisan demo:seed
```

Both commands run inside `sh -c` in `fly.toml`.

### Set secrets (example)

```bash
fly secrets set APP_KEY=base64:...
fly secrets set DATABASE_URL=postgres://...
fly secrets set OPENAI_API_KEY=sk-...
```

---

## API routes (short list)

**Public**

- `POST /api/login` — body: `{ "username", "password" }`
- `POST /api/register` — create household + admin user

**Protected** (need `Authorization: Bearer TOKEN`)

- `GET /api/me` — current user and household
- `POST /api/logout`
- `POST /api/household/members` — add member (admin)
- `PUT /api/household/members/{id}` — update member (admin)
- `DELETE /api/household/members/{id}` — remove member (admin)
- `GET|POST|PUT|DELETE /api/transactions`
- `GET|POST|PUT|DELETE /api/utilities`
- `GET|POST|PUT|DELETE /api/debts`
- `GET|POST|PUT|DELETE /api/savings`
- `GET|POST|PUT|DELETE /api/meters`
- `GET|POST|PUT|DELETE /api/business-orders`
- `GET|POST|PUT|DELETE /api/investments`
- `POST /api/ai/query` — AI chat
- `POST /api/ai/v1/...` — AI finance tools

Full list: see `routes/api.php`.

---

## CORS

The API allows browser requests from any origin (`config/cors.php`).  
The frontend sends JSON with a Bearer token.

---

## Related repo

- **Frontend (Next.js):** [github.com/kokenydaniel/osszhang-frontend](https://github.com/kokenydaniel/osszhang-frontend)

---

## License

Private project. All rights reserved.
