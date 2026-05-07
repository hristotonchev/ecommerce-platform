# Ecommerce Platform

Hybrid e-commerce platform built with **Nest.js** (API Gateway) and **Laravel** (Admin Panel), sharing a PostgreSQL database with real-time WebSocket support.

## Architecture

```
                    Internet
                        |
               [Nginx :80]
               /           \
 [Nest.js :3000]      [Laravel :8000]
  API Gateway          Admin Panel
       |                    |
       +--------+-----------+
                |
         [PostgreSQL]
         [Redis Cache]
```

Nest.js handles the public API — authentication, product browsing, order placement, WebSocket connections.

Laravel handles internal administration — product and category management, order processing, sales reports.

Both services share the same PostgreSQL database. When Laravel updates a product, it fires a webhook to Nest.js to invalidate the Redis cache. A single JWT secret is shared between both services.

## Tech Stack

| Layer      | Technology             |
|------------|------------------------|
| API        | Nest.js + TypeScript   |
| Admin      | Laravel 13 + Blade     |
| Database   | PostgreSQL 15          |
| Cache      | Redis 7                |
| Auth       | JWT (shared secret)    |
| Real-time  | Socket.io WebSockets   |
| Container  | Docker + Nginx         |

## Quick Start

Prerequisites: Docker, Node.js 22, PHP 8.4, Composer

```bash
# 1. Start infrastructure
git clone <repo>
cd ecommerce-platform
docker-compose up -d postgres redis

# 2. API Gateway
cd api-gateway
npm install
cp .env.example .env
npm run start:dev

# 3. Seed database (in a new terminal)
cd api-gateway
npm run seed

# 4. Admin Panel
cd admin-service
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --port=8000
```

## Default Credentials

| User          | Email                 | Password    | Role     |
|---------------|-----------------------|-------------|----------|
| Admin         | admin@shop.com        | password123 | admin    |
| Customer      | maria@example.com     | password123 | customer |
| Customer      | georgi@example.com    | password123 | customer |

Admin login works in both the Nest.js API and the Laravel admin panel without any extra steps.

## Services

| Service        | URL                       |
|----------------|---------------------------|
| Nest.js API    | http://localhost:3000     |
| Laravel Admin  | http://localhost:8000     |
| PostgreSQL     | localhost:5432            |
| Redis          | localhost:6379            |

## API Endpoints

### Auth
```
POST /api/auth/register
POST /api/auth/login
```

### Products
```
GET    /api/products           public, paginated, filterable
GET    /api/products/:id       public
POST   /api/products           admin only
PUT    /api/products/:id       admin only
DELETE /api/products/:id       admin only, soft delete
```

### Orders
```
POST /api/orders               authenticated, checks inventory
GET  /api/orders/my-orders     authenticated
GET  /api/orders/:id           authenticated
GET  /api/orders               admin only
PUT  /api/orders/:id/status    admin only
```

### Internal (Laravel to Nest.js)
```
POST /api/internal/cache/invalidate    X-API-Key required
POST /api/internal/orders/:id/status  X-API-Key required
```

## WebSockets

```javascript
import { io } from 'socket.io-client';

const socket = io('http://localhost:3000/orders', {
  auth: { token: 'your-jwt-token' }
});

socket.on('connected',     (data) => console.log(data));
socket.on('order_updated', (data) => console.log(data));
socket.on('order_created', (data) => console.log(data));
```

Events are emitted when an order is placed or its status changes.

## Admin Panel

Available at http://localhost:8000/admin

- Dashboard — revenue, order counts, recent activity
- Products — CRUD with image upload and soft deletes
- Categories — nested with parent/child support
- Orders — status updates, synced to Nest.js via webhook
- Users — customer list and order history
- Reports — daily/monthly sales, CSV export

## Testing

```bash
# Nest.js — 14 unit tests
cd api-gateway
npm run test

# Laravel — 21 feature tests
cd admin-service
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=ecommerce \
DB_USERNAME=ecommerce_user DB_PASSWORD=secret \
php artisan test
```

## Key Design Decisions

**Shared database** — Both services read and write to the same PostgreSQL instance. This avoids distributed transaction complexity while still allowing independent deployment.

**Cache invalidation via webhooks** — When Laravel updates a product, it calls Nest.js `/api/internal/cache/invalidate`. This keeps Redis fresh without waiting for TTL expiry.

**Shared JWT secret** — One `JWT_SECRET` in both `.env` files. Tokens issued by Nest.js are valid in Laravel API routes and vice versa.

**Inventory reservation** — On order placement, stock is `reserved` not deducted. Deduction happens on ship. Cancellations release the reservation.

**Soft deletes** — Products are never hard-deleted, preserving order history integrity.

**bcrypt compatibility** — The seeder generates PHP-compatible password hashes by replacing the `$2b$` prefix (Node.js) with `$2y$` (PHP). Both are identical algorithmically.

## Integration Flow

```
Product update:
Admin PUT /admin/products/:id  (Laravel)
  -> UPDATE products (PostgreSQL)
  -> POST /api/internal/cache/invalidate (Nest.js)
  -> Redis cache cleared
  -> Next GET returns fresh data

Order status update:
Admin PUT /admin/orders/:id  (Laravel)
  -> UPDATE orders (PostgreSQL)
  -> POST /api/internal/orders/:id/status (Nest.js)
  -> WebSocket push to customer
```

## Postman

Import from the docs/ folder:
- ecommerce-api.postman_collection.json
- ecommerce-local.postman_environment.json

Select the Ecommerce Local environment. Run Auth -> Login (Admin) first — token saves automatically.

## Project Structure

```
ecommerce-platform/
├── api-gateway/              Nest.js public API
│   ├── src/
│   │   ├── auth/             JWT, guards, decorators
│   │   ├── products/         CRUD with Redis cache
│   │   ├── orders/           Transactions, inventory
│   │   ├── internal/         Webhook endpoints
│   │   ├── websockets/       Real-time gateway
│   │   ├── entities/         TypeORM entities
│   │   └── database/         Seeders
│   └── docs/                 Postman collection
├── admin-service/            Laravel admin panel
│   ├── app/Http/Controllers/Admin/
│   ├── resources/views/admin/
│   └── tests/Feature/Admin/
├── nginx/nginx.conf
└── docker-compose.yml
```
