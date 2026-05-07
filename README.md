# Ecommerce Platform

Hybrid e-commerce platform built with **Nest.js** (API Gateway) and **Laravel** (Admin Panel), sharing a PostgreSQL database with real-time WebSocket support.

## Architecture

```
                    Internet
                       ↓
              [Nginx :80]
              /           \
[Nest.js :3000]      [Laravel :8000]
 API Gateway          Admin Panel
      |                    |
      └──────┬─────────────┘
             |
      [PostgreSQL]
      [Redis Cache]
```

**Nest.js** handles the public API — authentication, product browsing, order placement, WebSocket connections.

**Laravel** handles internal administration — product and category management, order processing, sales reports.

Both services share the same PostgreSQL database. When Laravel updates a product, it fires a webhook to Nest.js to invalidate the Redis cache. A single JWT secret is shared between both services.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| API Gateway | Nest.js + TypeScript |
| Admin Panel | Laravel 13 + Blade |
| Database | PostgreSQL 15 |
| Cache | Redis 7 |
| Auth | JWT (shared secret) |
| Real-time | Socket.io WebSockets |
| Container | Docker + Nginx |

## Quick Start

**Prerequisites:** Docker, Node.js 22, PHP 8.4, Composer

```bash
# 1. Clone and start infrastructure
git clone <repo>
cd ecommerce-platform
docker-compose up -d postgres redis

# 2. Start Nest.js API
cd api-gateway
npm install
cp .env.example .env
npm run start:dev

# 3. Seed database
npm run seed

# 4. Start Laravel Admin
cd ../admin-service
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --port=8000
```

## Services

| Service | URL | Credentials |
|---------|-----|-------------|
| Nest.js API | http://localhost:3000 | JWT token |
| Laravel Admin | http://localhost:8000 | laravel-admin@test.com / password123 |
| PostgreSQL | localhost:5432 | ecommerce_user / secret |
| Redis | localhost:6379 | — |

## API Endpoints

### Authentication
```
POST /api/auth/register   — Register new user
POST /api/auth/login      — Login, returns JWT token
```

### Products (public)
```
GET    /api/products        — List with pagination & filters
GET    /api/products/:id    — Single product
POST   /api/products        — Create (Admin only)
PUT    /api/products/:id    — Update (Admin only)
DELETE /api/products/:id    — Soft delete (Admin only)
```

### Orders (authenticated)
```
POST /api/orders              — Place order (checks inventory)
GET  /api/orders/my-orders    — User's orders
GET  /api/orders/:id          — Single order
GET  /api/orders              — All orders (Admin only)
PUT  /api/orders/:id/status   — Update status (Admin only)
```

### Internal (Laravel → Nest.js, API Key protected)
```
POST /api/internal/cache/invalidate    — Invalidate product cache
POST /api/internal/orders/:id/status  — Sync order status
```

## WebSockets

Connect to `ws://localhost:3000/orders` with JWT token:

```javascript
import { io } from 'socket.io-client';

const socket = io('http://localhost:3000/orders', {
  auth: { token: 'your-jwt-token' }
});

socket.on('connected', (data) => console.log(data));
socket.on('order_updated', (data) => console.log(data));
socket.on('order_created', (data) => console.log(data));
```

Events are emitted on order placement and status changes in real time.

## Admin Panel

Available at `http://localhost:8000/admin`:

- **Dashboard** — revenue, order counts, recent activity
- **Products** — CRUD with image upload, soft deletes, category assignment
- **Categories** — nested category management with parent/child support
- **Orders** — status management, order details, webhook sync to Nest.js
- **Users** — customer list and order history
- **Reports** — daily/monthly sales analytics, CSV export

## Testing

```bash
# Nest.js unit tests (14 passing)
cd api-gateway
npm run test

# Laravel feature tests (21 passing)
cd admin-service
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=ecommerce \
DB_USERNAME=ecommerce_user DB_PASSWORD=secret \
php artisan test
```

## Database Seeding

```bash
cd api-gateway
npm run seed
```

Creates default users, categories, and products for development.

Default credentials after seeding:
- Admin: `admin@ecommerce.com` / `password123`
- Customer: `john@example.com` / `password123`

## Key Design Decisions

**Shared database over microservice isolation**
For this scale, sharing PostgreSQL between services avoids the complexity of distributed transactions while still allowing independent deployment. The two services communicate via webhooks for cache invalidation.

**Redis caching with event-driven invalidation**
Product reads are cached in Redis with a 5-minute TTL. When Laravel admin updates a product, it fires a POST to Nest.js `/api/internal/cache/invalidate`, ensuring the mobile API always returns fresh data without waiting for TTL expiry.

**JWT shared secret**
A single `JWT_SECRET` environment variable is read by both services. Tokens issued by Nest.js are valid in Laravel's `/api/*` routes, enabling a unified authentication experience.

**Optimistic inventory reservation**
When an order is placed, inventory is `reserved` (not deducted) until the order is shipped. Cancellations release the reservation. This prevents overselling without requiring pessimistic locking on every read.

**Soft deletes on products**
Products are never hard-deleted. This preserves order history integrity — an order item always has a valid product reference even after the product is removed from the catalog.

**CQRS-inspired separation**
Write operations (order placement, product updates) go through Nest.js with full validation and transaction support. Read operations use Redis cache first, falling back to the read replica pattern when needed.

## Integration Flow

```
Product Update Flow:
Admin → PUT /admin/products/:id (Laravel)
      → UPDATE products SET ... (PostgreSQL)
      → POST /api/internal/cache/invalidate (Nest.js)
      → DELETE product_N from Redis
      → Next GET /api/products/:id fetches fresh from DB

Order Status Flow:
Admin → PUT /admin/orders/:id (Laravel)
      → UPDATE orders SET status=... (PostgreSQL)
      → POST /api/internal/orders/:id/status (Nest.js)
      → WebSocket push to user's mobile app
```

## Postman Collection

Import both files from the `docs/` folder into Postman:
- `ecommerce-api.postman_collection.json`
- `ecommerce-local.postman_environment.json`

Select the **Ecommerce Local** environment, then run **Auth → Login (Admin)** first. The JWT token is saved automatically to all subsequent requests.

## Project Structure

```
ecommerce-platform/
├── api-gateway/          # Nest.js — public API
│   ├── src/
│   │   ├── auth/         # JWT auth, guards, decorators
│   │   ├── products/     # Product CRUD with caching
│   │   ├── orders/       # Order processing with transactions
│   │   ├── internal/     # Internal webhooks from Laravel
│   │   ├── websockets/   # Real-time order updates
│   │   ├── entities/     # TypeORM entities
│   │   └── database/     # Seeders
│   └── docs/             # Postman collection
├── admin-service/        # Laravel — admin panel
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/  # Dashboard, Products, Orders...
│   │   │   └── Middleware/         # AdminMiddleware, JwtMiddleware
│   │   └── Models/
│   ├── resources/views/admin/      # Blade templates
│   └── tests/Feature/Admin/        # Feature tests
├── nginx/
│   └── nginx.conf        # Reverse proxy config
└── docker-compose.yml    # Full stack definition
```
