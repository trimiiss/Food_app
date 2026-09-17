# FoodApp — Food Ordering Application

A full-stack food ordering app: customers browse a menu by category, build a cart, check out and
track their orders; administrators manage products, categories and orders from an admin panel.

| Layer    | Tech                                                                      |
| -------- | ------------------------------------------------------------------------- |
| Backend  | PHP 8.4 · **Laravel 13** · Laravel **Sanctum** (token auth) · REST API    |
| Frontend | **React 19** SPA · **Vite 8** · React Router 8 · axios                    |
| Database | **MySQL 8** in **Docker** (via `docker-compose.yml`), schema via migrations |

```
Food_app/
├── backend/             Laravel REST API (api/v1/*)
├── frontend/            React SPA (Vite)
├── docker-compose.yml   MySQL 8 only
└── README.md
```

---

## Contents

1. [Features](#features)
2. [Architecture & design decisions](#architecture--design-decisions)
3. [Prerequisites](#prerequisites)
4. [Install & run](#install--run)
5. [Demo accounts](#demo-accounts)
6. [Running the tests](#running-the-tests)
7. [API reference](#api-reference)
8. [Database schema](#database-schema)
9. [Known limitations](#known-limitations)

---

## Features

| # | Requirement | Where |
| - | ----------- | ----- |
| 1 | Admin registration & login (Sanctum) | `/admin/login`, `/admin/register` (invite code) · also customer `/login`, `/register` |
| 2 | Public product listing | `/` — all available products, search, pagination |
| 3 | Product categories | Category chips on `/` (`?category=pizza`), category on every product |
| 4 | Add to cart | Product cards and `/products/:slug` (with quantity) |
| 5 | Change quantity / remove from cart | `/cart` — stepper, typed quantity, remove, clear |
| 6 | Place an order | `/checkout` → `POST /api/v1/orders` (server re-prices the cart) |
| 7 | View past orders | `/orders` (history) and `/orders/:id` (detail) |
| 8 | Order status tracking | State machine `pending → confirmed → preparing → out_for_delivery → delivered` (or `cancelled`); live timeline on `/orders/:id` |
| 9 | Admin panel | `/admin` dashboard · products CRUD · categories CRUD · all orders with status updates |

Also included: seeded demo data (6 categories, 24 products, 4 orders), 69 backend tests, customer
order cancellation (before preparation starts), and a dashboard with order/revenue stats.

---

## Architecture & design decisions

```
 React SPA (Vite, :5173)                         Laravel API (:8000)                 MySQL 8 (Docker, :3306)
 ┌───────────────────────────┐   HTTPS/JSON      ┌───────────────────────────────┐   ┌─────────────────────┐
 │ pages / components        │   Bearer token    │ routes/api.php  (/api/v1)     │   │ users               │
 │ AuthContext  CartContext  │ ───────────────►  │ middleware: auth:sanctum,admin│   │ categories          │
 │ ShopContext  useApi hook  │                   │ Form Requests (validation)    │──►│ products            │
 │ api/client.js (axios)     │ ◄───────────────  │ Controllers → OrderService    │   │ orders, order_items │
 └───────────────────────────┘   JSON / errors   │ API Resources (JSON shape)    │   │ personal_access_... │
   cart lives in localStorage                    └───────────────────────────────┘   └─────────────────────┘
```

**Laravel serves a REST API only** under `/api/v1` (versioned so a breaking change can ship as `v2`),
and the React app is a completely separate SPA that calls it cross-origin — CORS is restricted to the
SPA's origin (`FRONTEND_URL`). Only MySQL runs in Docker; PHP and Node run on the host so the dev
loop (artisan, Vite HMR) stays fast.

### Backend

- **Sanctum, not Passport.** Passport is a full OAuth2 server (clients, grants, keys). There is exactly
  one first-party client — this SPA — so Sanctum's personal access tokens give the same result with a
  single table and no OAuth ceremony. It's Laravel's own recommendation for SPAs.
- **Sanctum token mode, not cookie mode.** The SPA (`:5173`) and API (`:8000`) are different origins;
  bearer tokens avoid CSRF-cookie and same-site-domain configuration. Trade-off: the token is kept in
  `localStorage`, which XSS could read — in production on a shared domain, Sanctum's cookie mode is the
  safer choice. Tokens expire after 7 days (`SANCTUM_EXPIRATION`).
- **One `users` table with a `role` (`admin` | `customer`).** One guard, one login flow, and
  `orders.user_id` stays a plain foreign key. `role` is **not mass-assignable**, and public registration
  always creates customers, so nobody can self-promote by posting `"role": "admin"`.
- **Admin registration is gated by an invite code** (`ADMIN_REGISTRATION_CODE`, compared in constant
  time). An open "register as admin" endpoint would let anyone take over the shop. Leave the variable
  empty to disable admin sign-up entirely (403).
- **`OrderStatus` enum is the state machine.** Allowed transitions live in one place
  (`app/Enums/OrderStatus.php`); both the admin status endpoint and customer cancellation go through
  `OrderService::transition()`, which locks the row so two admins can't race. Invalid jumps
  (e.g. `delivered → pending`) return `422`. Every order in the API includes `allowed_transitions`
  and `can_cancel`, so the UI never re-implements the rules.
- **Checkout never trusts client prices.** The request contains only `product_id` + `quantity`; the
  server reloads products, re-checks availability, and computes totals in **integer cents** (no float
  drift), inside a DB transaction.
- **Order items snapshot `product_name` and `unit_price`.** Editing or deleting a product later never
  changes what a customer was charged (`product_id` is `SET NULL` on delete).
- **Deleting a category that still has products is refused** (`409` with an explanation) rather than
  cascading and silently wiping the menu.
- **Status/role are strings cast to PHP enums**, not native MySQL `ENUM` columns — adding a status
  needs no `ALTER TABLE`, and the SQLite test database has no native enum type.
- **Consistent JSON errors** for every `/api/*` request, even without an `Accept` header
  (`ForceJsonResponse` middleware — otherwise an unauthenticated request would try to redirect to a
  non-existent login page and 500).
- **Thin controllers**: validation in Form Requests, response shape in API Resources, order logic in
  `OrderService`, ownership via `OrderPolicy` (another customer's order is a `404`, not a `403`, so
  sequential ids don't leak which orders exist).

### Frontend

- **Vite, not Create React App.** CRA is unmaintained and no longer recommended by the React docs;
  Vite starts instantly, has fast HMR and needs almost no config. Next.js would add SSR/routing
  conventions we don't need — Laravel already owns the server side.
- **Plain JavaScript + plain CSS**, no UI kit, to keep the submission small and easy to review.
- **Cart is client-side** (React context + `useReducer`, persisted to `localStorage`). A pre-checkout
  cart is ephemeral; persisting it server-side would force guest sessions or login-before-browsing.
  Cart prices are shown as an *estimate*; the order confirmation shows the server's totals.
- **axios instance with interceptors** attaches the token and normalises every failure into
  `ApiError {status, message, errors}`; on a `401` it drops the stale token and signs the user out.
- **Server-side validation is the single source of truth** — forms display Laravel's 422 field errors
  next to the matching inputs rather than duplicating rules in JS.
- **Filters live in the URL** (`?category=pizza&search=…&page=2`), so views are shareable and the back
  button works. Order tracking **polls every 15 s** while an order is still in progress (WebSockets
  would be the production answer).
- **Route guards are UX only** — the API enforces `auth:sanctum` + `admin` on every protected endpoint.

---

## Prerequisites

| Tool | Version | Notes |
| ---- | ------- | ----- |
| **Docker** | Docker Desktop 4.x / Engine 24+ with **Compose v2** | Runs MySQL |
| **PHP** | **8.3+** (developed on 8.4) | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `curl`, `zip` (+ `pdo_sqlite` to run the tests) |
| **Composer** | 2.x | |
| **Node.js** | **20.19+ or 22.12+** (developed on 24) | Required by Vite 8 |
| npm | 10+ | Ships with Node |

> **MySQL does not need to be installed.** It runs in the Docker container defined in
> `docker-compose.yml`, with credentials that already match `backend/.env.example`.

---

## Install & run

### 1. Start MySQL (Docker)

```bash
git clone <repo-url> Food_app
cd Food_app
docker compose up -d --wait     # --wait blocks until MySQL's healthcheck passes
```

This starts `mysql:8.0` as `foodapp-mysql` on port **3306** with database `food_app`,
user `food_user` / `food_secret` (root password `root_secret`). Data persists in the named volume
`foodapp_mysql_data`; `docker compose down -v` wipes it.

> Port 3306 already in use? Run `MYSQL_PORT=3307 docker compose up -d --wait` and set `DB_PORT=3307`
> in `backend/.env`.

### 2. Backend (Laravel API)

```bash
cd backend
composer install
cp .env.example .env            # Windows (cmd): copy .env.example .env
php artisan key:generate
php artisan migrate --seed      # creates all tables and loads demo data
php artisan serve               # → http://127.0.0.1:8000
```

`http://127.0.0.1:8000/api/v1/products` should now return JSON.
To reset the demo data at any time: `php artisan migrate:fresh --seed`.

### 3. Frontend (React SPA)

In a second terminal:

```bash
cd frontend
npm install
npm run dev                     # → http://localhost:5173
```

The SPA calls `http://127.0.0.1:8000/api/v1` by default. To point it elsewhere, copy
`frontend/.env.example` to `frontend/.env` and change `VITE_API_URL`. The dev server is pinned to port
5173 because that is the origin the API's CORS config allows (`FRONTEND_URL` in `backend/.env`).

---

## Demo accounts

Created by the seeder (`database/seeders/UserSeeder.php`):

| Role | Email | Password | Log in at |
| ---- | ----- | -------- | --------- |
| **Admin** | `admin@foodapp.test` | `password` | http://localhost:5173/admin/login |
| Customer | `customer@foodapp.test` | `password` | http://localhost:5173/login |

- The demo customer already has 4 orders (delivered, preparing, pending) to show order history and tracking.
- To register a **new admin** at `/admin/register`, use the registration code from `backend/.env`:
  `ADMIN_REGISTRATION_CODE=letmein-admin`.
- "Truffle Mushroom Burger" is seeded as **unavailable**: hidden from the storefront, visible in the admin panel.

**Quick tour:** add a few dishes to the cart → check out as the demo customer → open the order page →
in another browser/incognito window log in as admin → *Orders* → advance the order's status → watch the
customer's timeline update within 15 s.

---

## Running the tests

```bash
cd backend
php artisan test                # 69 tests — uses in-memory SQLite, Docker not required
```

Covered: auth (role escalation attempts, invite code, token revocation, 401 JSON without `Accept`),
catalogue filtering and availability, checkout pricing (client prices ignored, integer-cent totals,
snapshots survive product edits), order ownership (404 for others' orders), cancellation rules,
admin authorization matrix (401 anonymous / 403 customer on every admin route), CRUD validation,
409 on non-empty category delete, the status state machine, and the seeder (documented credentials work,
re-seeding doesn't duplicate).

```bash
cd frontend
npm run lint                    # oxlint
npm run build                   # production build
```

---

## API reference

Base URL: `http://127.0.0.1:8000/api/v1` · JSON in/out · protected routes need
`Authorization: Bearer <token>` (returned by register/login).

### Public

| Method | Endpoint | Description |
| ------ | -------- | ----------- |
| `POST` | `/register` | Create a **customer** account → `{ data: { user, token } }` |
| `POST` | `/admin/register` | Create an **admin** account; requires `registration_code` |
| `POST` | `/login` | Log in (customer or admin) → `{ data: { user, token } }` |
| `GET`  | `/shop` | Storefront settings: `currency`, `delivery_fee`, `max_item_quantity` |
| `GET`  | `/categories` | All categories with count of available products |
| `GET`  | `/products?category={slug}&search={text}&page={n}&per_page={n}` | Available products (paginated) |
| `GET`  | `/products/{slug}` | One available product |

Auth endpoints are rate-limited to 10 requests/minute.

### Authenticated (any role)

| Method | Endpoint | Description |
| ------ | -------- | ----------- |
| `GET`  | `/me` | Current user |
| `POST` | `/logout` | Revoke the current token |
| `POST` | `/orders` | Place an order (see below) |
| `GET`  | `/orders?page={n}` | Current user's orders, newest first |
| `GET`  | `/orders/{id}` | One of the current user's orders |
| `POST` | `/orders/{id}/cancel` | Cancel own order (only while `pending`/`confirmed`) |

```jsonc
// POST /api/v1/orders — prices are never sent; the server computes them
{
  "items": [{ "product_id": 1, "quantity": 2 }, { "product_id": 7, "quantity": 1 }],
  "delivery_address": "221B Baker Street, London",
  "contact_phone": "+44 20 7946 0958",
  "notes": "Ring the bell"
}
```

### Admin only (`auth:sanctum` + `admin`)

| Method | Endpoint | Description |
| ------ | -------- | ----------- |
| `GET` | `/admin/stats` | Dashboard numbers, orders by status, recent orders |
| `GET` · `POST` | `/admin/categories` | List (with product counts) · create |
| `GET` · `PUT` · `DELETE` | `/admin/categories/{id}` | Show · update · delete (`409` if it has products) |
| `GET` · `POST` | `/admin/products?category=&search=&page=` | List **all** products (incl. unavailable) · create |
| `GET` · `PUT` · `DELETE` | `/admin/products/{id}` | Show · update · delete |
| `GET` | `/admin/orders?status=&search=&page=` | All orders (search: order number, customer name/email) + `statuses` list |
| `GET` | `/admin/orders/{id}` | Order with items and customer |
| `PATCH` | `/admin/orders/{id}/status` | `{ "status": "preparing" }` — must be an allowed transition |

### Order status state machine

```
pending ──► confirmed ──► preparing ──► out_for_delivery ──► delivered
   │            │             │                │
   └────────────┴─────────────┴────────────────┴──────────► cancelled
```

`delivered` and `cancelled` are final. Customers can cancel only while `pending` or `confirmed`.

### Errors

| Status | When | Body |
| ------ | ---- | ---- |
| `401` | Missing/invalid/expired token | `{ "message": "Unauthenticated." }` |
| `403` | Customer calling an admin endpoint; admin registration disabled | `{ "message": "…" }` |
| `404` | Unknown resource, unavailable product, another customer's order | `{ "message": "Product not found." }` |
| `409` | Deleting a category that still has products | `{ "message": "Cannot delete \"Pizza\" because it still has 5 products. …" }` |
| `422` | Validation failed, wrong credentials, illegal status transition | `{ "message": "…", "errors": { "field": ["…"] } }` |
| `429` | Too many auth attempts | `{ "message": "Too Many Attempts." }` |

> With `APP_DEBUG=true` (the local default) Laravel also includes debug details such as a stack trace
> in error bodies; set `APP_DEBUG=false` to get only the shapes above.

---

## Database schema

All tables are created by Laravel migrations in `backend/database/migrations` (no hand-written SQL).

```
users           id, name, email (unique), password, role ['admin'|'customer'], timestamps
categories      id, name, slug (unique), description?, timestamps
products        id, category_id → categories (RESTRICT), name, slug (unique), description?,
                price decimal(10,2), image_url?, is_available, timestamps
orders          id, user_id → users (RESTRICT), order_number (unique), status, subtotal,
                delivery_fee, total, delivery_address, contact_phone, notes?, timestamps
order_items     id, order_id → orders (CASCADE), product_id → products (SET NULL),
                product_name, unit_price, quantity, line_total, timestamps
personal_access_tokens   (Sanctum)
```

Relationships: `Category hasMany Product` · `User hasMany Order` · `Order hasMany OrderItem` ·
`OrderItem belongsTo Product` (nullable).

---

## Known limitations

This is a technical-challenge submission and intentionally **not production-ready**. Deliberately left out:

- **Payments** — orders are placed without payment.
- **Stock/inventory** — availability is a manual on/off switch per product.
- **Image uploads** — products use an image URL (seed images are hot-linked from Unsplash; a placeholder
  is shown if one fails to load).
- **Real-time updates** — order tracking polls every 15 s instead of using WebSockets.
- **Token storage** — bearer token in `localStorage` (see the Sanctum decision above).
- **Email verification / password reset** — not implemented.
- **Frontend tests** — the backend has a committed automated suite; the SPA's flows were checked with
  scripted browser runs during development, but no frontend test suite is committed.
