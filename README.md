# LeuEats — Food Ordering Application

Customers browse a menu by category, fill a cart, place orders and follow their status.
Administrators manage products, categories and orders from an admin panel.

**Stack:** Laravel 13 (PHP) REST API with Sanctum auth · React 19 + Vite 8 · MySQL 8 in Docker

```
backend/             Laravel API (/api/v1)
frontend/            React app
docker-compose.yml   MySQL (+ optional Adminer)
```

## Features

- Registration for customers, and for admins with an invite code; one login page for both,
  and admins land on the admin panel
- Product listing with search and categories
- Cart: add products, change quantities, remove items
- Checkout: place an order for delivery or pickup
- Customer order history and order status
  (`pending → confirmed → preparing → out_for_delivery → delivered`, or `cancelled`)
- Admin panel: add, edit and delete products and categories; manage orders and change their status
- Extras: offers, promo codes, a deals page, analytics with CSV export

## Requirements

- Docker with Compose v2 (runs MySQL, so MySQL doesn't need to be installed)
- PHP 8.3+ with `pdo_mysql` (plus `pdo_sqlite` for the tests), and Composer 2
- Node.js 20.19+ or 22.12+

## Installation and running

**1. Database**

```bash
docker compose up -d --wait
```

This starts MySQL on port 3306 with database `food_app`, user `food_user` and password
`food_secret`, the same values that are in `backend/.env.example`.

> Is port 3306 taken (e.g. by XAMPP)? Run `MYSQL_PORT=3307 docker compose up -d --wait` and set
> `DB_PORT=3307` in `backend/.env`.

**2. Backend** (first terminal)

```bash
cd backend
composer install
cp .env.example .env          # Windows cmd: copy .env.example .env
php artisan key:generate
php artisan migrate --seed    # creates the tables and loads demo data
php artisan serve             # http://127.0.0.1:8000
```

**3. Frontend** (second terminal)

```bash
cd frontend
npm install
npm run dev                   # http://localhost:5173
```

Then open **http://localhost:5173**.

## Demo accounts

Both log in at `/login`.

| Role | Email | Password |
| ---- | ----- | -------- |
| Admin | `admin@leueats.test` | `password` |
| Customer | `customer@leueats.test` | `password` |

To register a new admin at `/admin/register`, use the code `letmein-admin`
(`ADMIN_REGISTRATION_CODE` in `backend/.env`).

## Useful commands

```bash
cd backend && php artisan migrate:fresh --seed    # reset the demo data
cd backend && php artisan test                    # backend tests (in-memory SQLite, no Docker needed)
docker compose --profile tools up -d adminer      # database UI at http://localhost:8080
```

To log in to Adminer, use server `mysql` with the database credentials above.

## Deployment

[`render.yaml`](render.yaml) deploys the database, the API and the frontend to Render
(**New → Blueprint**).
