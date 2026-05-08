# Ecommerce Platform

Hybrid e-commerce platform built with **Nest.js** (API Gateway) and **Laravel** (Admin Panel), sharing a PostgreSQL database. Features real-time WebSocket updates, GraphQL API alongside REST, Elasticsearch advanced search, JWT authentication shared between both services, Redis caching with webhook-based invalidation, queue-based job processing, and a full CI/CD pipeline.

---

## Architecture

```
                              Internet
                                  |
                           [Nginx :80]
                          /            \
            [Nest.js :3000]        [Laravel :8000]
             API Gateway             Admin Panel
                  |                       |
                  |    Webhooks (HTTP)     |
                  +<----------------------+
                  |                       |
                  +----------+-----------+
                             |
                    [PostgreSQL :5432]
                    [Redis :6379]
                    [Elasticsearch :9200]
```

### Request Flow

```
Mobile / External Client
        |
        | REST or GraphQL
        v
[Nest.js API :3000]
  - JWT authentication
  - Role-based guards
  - Redis cache (products)
  - Elasticsearch (search)
  - WebSocket gateway
  - Request logging middleware
  - Email queue jobs
        |
        v
[PostgreSQL] <────────────────── [Laravel Admin :8000]
                                    - Session auth (admin only)
                                    - Product/Category CRUD
                                    - Image upload + queue processing
                                    - Order management
                                    - Reports (CSV + PDF)
                                    - Bulk import/export CSV
                                    - Fires webhooks to Nest.js
```

### Service Communication

```
Product update (Laravel → Nest.js):
  1. UPDATE products in PostgreSQL
  2. POST /api/internal/cache/invalidate → Redis cleared
  3. ProcessProductImage job → queue worker

Order status update (Laravel → Nest.js → Customer):
  1. UPDATE orders in PostgreSQL
  2. POST /api/internal/orders/:id/status → Nest.js
  3. WebSocket push to customer

Order placement (Nest.js):
  1. Transaction: check inventory, create order, reserve stock
  2. Mock payment processing
  3. OrderConfirmationJob → async email
  4. WebSocket push to customer + broadcast
```

---

## Tech Stack

| Layer         | Technology                             |
|---------------|----------------------------------------|
| API           | Nest.js 11 + TypeScript                |
| Admin         | Laravel 13 + Blade + Tailwind CSS      |
| Database      | PostgreSQL 15                          |
| Cache         | Redis 7                                |
| Search        | Elasticsearch 8.13                     |
| Search UI     | Kibana 8.13                            |
| Auth          | JWT HS256 (shared secret)              |
| Real-time     | Socket.io WebSockets                   |
| GraphQL       | Apollo Server + @nestjs/graphql        |
| Queue (API)   | Async in-process jobs (Node.js)        |
| Queue (Admin) | Laravel Queues with Redis driver       |
| Container     | Docker + Docker Compose + Nginx        |
| CI/CD         | GitHub Actions                         |

---

## Quick Start

**Prerequisites:** Docker Desktop, Node.js 22, PHP 8.4, Composer

```bash
# 1. Clone repository
git clone https://github.com/hristotonchev/ecommerce-platform.git
cd ecommerce-platform

# 2. Start infrastructure
docker-compose up -d postgres redis elasticsearch

# 3. Setup Nest.js API
cd api-gateway
npm install
cp .env.example .env
npm run start:dev

# New terminal — seed database
cd api-gateway
npm run seed

# Index products in Elasticsearch
curl http://localhost:3000/api/search/reindex

# 4. Setup Laravel Admin
cd admin-service
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --port=8000

# New terminal — start queue worker
cd admin-service
DB_USERNAME=ecommerce_user DB_PASSWORD=secret php artisan queue:work --verbose
```

---

## Default Credentials

| Role     | Email               | Password    | Access                |
|----------|---------------------|-------------|-----------------------|
| Admin    | admin@shop.com      | password123 | API + Admin Panel     |
| Customer | maria@example.com   | password123 | API only              |
| Customer | georgi@example.com  | password123 | API only              |

> The seeder generates PHP-compatible bcrypt hashes (`$2y$` prefix) so users work in both Nest.js and Laravel without extra steps.

---

## Services

| Service        | URL                           | Notes                     |
|----------------|-------------------------------|---------------------------|
| Nest.js API    | http://localhost:3000         | REST + GraphQL + WS       |
| GraphQL        | http://localhost:3000/graphql | Apollo Playground         |
| Laravel Admin  | http://localhost:8000/admin   | Session auth, admin only  |
| Elasticsearch  | http://localhost:9200         | Search engine             |
| Kibana         | http://localhost:5601         | Elasticsearch UI          |
| PostgreSQL     | localhost:5432                | ecommerce_user / secret   |
| Redis          | localhost:6379                | Cache + Queues            |
| Nginx          | http://localhost:80           | Reverse proxy             |

---

## REST API Endpoints

### Authentication
```
POST /api/auth/register    Register new user, returns JWT
POST /api/auth/login       Login, returns JWT
```

### Products
```
GET    /api/products              Paginated list with search and filters
GET    /api/products/:id          Single product with category and inventory
POST   /api/products              Create — Admin only
PUT    /api/products/:id          Update — Admin only
DELETE /api/products/:id          Soft delete — Admin only
```

### Search — Elasticsearch
```
GET /api/search/products    Full-text fuzzy search with filters and aggregations
GET /api/search/reindex     Reindex all products
```

Parameters: `q`, `category_id`, `min_price`, `max_price`, `page`, `limit`

### Orders
```
POST /api/orders              Place order — inventory check + mock payment + email job
GET  /api/orders/my-orders    Current user orders
GET  /api/orders/:id          Single order
GET  /api/orders              All orders — Admin only
PUT  /api/orders/:id/status   Update status — Admin only
```

### Internal — X-API-Key required
```
POST /api/internal/cache/invalidate       Laravel → invalidate Redis
POST /api/internal/orders/:id/status     Laravel → sync + WebSocket push
```

### Shared JWT — Laravel API
```
GET /api/me              User profile (validates Nest.js JWT in Laravel)
GET /api/user/orders     User orders (validates Nest.js JWT in Laravel)
```

---

## GraphQL

Available at **http://localhost:3000/graphql**

Add header: `{ "Authorization": "Bearer your-jwt-token" }`

```graphql
# Public
query {
  products(filter: { page: 1, limit: 10, search: "iPhone" }) {
    total page last_page
    data { id name price category { name } inventory { quantity } }
  }
}

# Authenticated
query {
  myOrders {
    id status total_amount
    items { quantity unit_price subtotal }
  }
}

# Admin only
mutation {
  createProduct(input: {
    name: "New Product" description: "Description"
    price: 99.99 category_id: 1 quantity: 50
  }) { id name price }
}
```

---

## WebSockets

```javascript
import { io } from 'socket.io-client';

const socket = io('http://localhost:3000/orders', {
  auth: { token: 'your-jwt-token' }
});

socket.on('connected',     (data) => console.log(data.message));
socket.on('order_updated', (data) => console.log(`Order #${data.orderId} → ${data.status}`));
socket.on('order_created', (data) => console.log(`New order #${data.orderId}`));
```

---

## Elasticsearch

```bash
# Fuzzy search
curl "http://localhost:3000/api/search/products?q=iphone"

# Price range
curl "http://localhost:3000/api/search/products?q=laptop&min_price=1000&max_price=3000"

# Category filter
curl "http://localhost:3000/api/search/products?category_id=4"

# Reindex
curl "http://localhost:3000/api/search/reindex"
```

Response includes highlights, relevance scores, price stats, and category aggregations.

---

## Queue Jobs

### OrderConfirmationJob (Nest.js)
Dispatched async after order placement. Mock email with order details. Non-blocking.

### ProcessProductImage (Laravel)
Dispatched when product image is uploaded. 3 retries, failure logging.

```bash
DB_USERNAME=ecommerce_user DB_PASSWORD=secret php artisan queue:work --verbose
```

---

## Admin Panel

**http://localhost:8000/admin** — admin role required

| Section    | Features                                                              |
|------------|-----------------------------------------------------------------------|
| Dashboard  | Revenue, order counts, pending orders, recent activity               |
| Products   | CRUD, image upload (queue), soft delete/restore, bulk import + export CSV |
| Categories | Nested categories, delete protection                                  |
| Orders     | Status management, webhook sync → WebSocket push to customer         |
| Users      | Customer list, per-user order history                                 |
| Reports    | Daily/monthly sales, top products, CSV export, PDF export            |

---

## Testing

### Nest.js — 14 unit tests

```bash
cd api-gateway
npm run test
```

### Laravel — 21 feature tests

```bash
cd admin-service
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=ecommerce \
DB_USERNAME=ecommerce_user DB_PASSWORD=secret \
php artisan test
```

---

## Database

### Seeding

```bash
cd api-gateway
npm run seed
```

Creates 3 users, 5 categories (2 nested), 5 products, 2 sample orders.

Reset:
```bash
docker exec -it ecommerce-platform-postgres-1 psql \
  -U ecommerce_user -d ecommerce \
  -c "TRUNCATE order_items, orders, inventory, products, categories, users RESTART IDENTITY CASCADE;"
npm run seed
curl http://localhost:3000/api/search/reindex
```

### Migrations

Laravel migration files are in `admin-service/database/migrations/` covering all 6 tables with proper foreign keys and constraints. The database schema is managed by TypeORM (`synchronize: true`) in development.

---

## CI/CD

### ci.yml — every push and PR

```
nestjs  → npm ci → 14 unit tests → TypeScript build
laravel → composer install → 21 feature tests
docker  → build both images → validate docker-compose
```

### cd.yml — push to main only

```
Build production assets
Tag Docker images with git SHA
Ready for deployment
```

---

## Key Design Decisions

**Shared database** — Both services share PostgreSQL. Avoids distributed transactions while allowing independent deployment.

**Redis + Elasticsearch** — Redis for exact lookups (O(1)). Elasticsearch for full-text with fuzzy matching, boosted fields (name^3), aggregations.

**Webhook cache invalidation** — Laravel calls Nest.js on every product change. No stale data.

**Shared JWT** — One `JWT_SECRET`. Tokens from Nest.js valid in Laravel `/api/*` routes.

**bcrypt compatibility** — Seeder uses `$2y$` prefix (PHP-compatible). Same algorithm as Node.js `$2b$`.

**Inventory reservation** — Stock reserved on order, deducted on ship, released on cancel.

**Soft deletes** — Products never hard-deleted. Order history always intact.

**Form Requests** — `StoreProductRequest`, `UpdateProductRequest`, `StoreOrderStatusRequest`, `StoreCategoryRequest` with authorization and custom messages.

**Laravel Resources** — `ProductResource`, `OrderResource`, `UserResource` for consistent API responses.

**Queue jobs** — Non-blocking email and image processing in both services.

---

## Implemented Requirements

| Requirement | Status |
|-------------|--------|
| Database schema + relationships | ✅ |
| Migration files (both frameworks) | ✅ |
| Nest.js + TypeORM + JWT | ✅ |
| class-validator DTOs | ✅ |
| Role-based guards | ✅ |
| Global error handling | ✅ |
| Products CRUD + pagination | ✅ |
| Redis caching | ✅ |
| Request logging middleware | ✅ |
| Unit tests (14) | ✅ |
| Order + inventory + payment | ✅ |
| Email queue job | ✅ |
| Laravel Breeze + admin middleware | ✅ |
| Dashboard metrics | ✅ |
| Product CRUD + image upload | ✅ |
| Nested categories | ✅ |
| Bulk import + export CSV | ✅ |
| Soft deletes + restore | ✅ |
| Laravel Resources | ✅ |
| Form Request validation | ✅ |
| Image upload queue job | ✅ |
| Eloquent relationships | ✅ |
| Order listing + filters | ✅ |
| Order status management | ✅ |
| Sales reports daily/monthly | ✅ |
| CSV export | ✅ |
| PDF export | ✅ |
| Service webhooks + API key | ✅ |
| Shared JWT | ✅ |
| Docker + Nginx | ✅ |
| DB seeders | ✅ |
| Feature tests (21) | ✅ |

## Bonus (5/5)

| Bonus | Status |
|-------|--------|
| WebSockets real-time | ✅ |
| GraphQL alongside REST | ✅ |
| Elasticsearch advanced search | ✅ |
| Docker Compose all services | ✅ |
| CI/CD pipeline | ✅ |

---

## Project Structure

```
ecommerce-platform/
├── .github/workflows/
│   ├── ci.yml
│   └── cd.yml
├── api-gateway/
│   ├── src/
│   │   ├── auth/            JWT, guards (REST + GQL), decorators
│   │   ├── products/        CRUD, Redis cache
│   │   ├── orders/          Transactions, inventory, email job, WebSocket
│   │   ├── internal/        Webhook endpoints
│   │   ├── websockets/      Socket.io real-time gateway
│   │   ├── search/          Elasticsearch service + controller
│   │   ├── graphql/         Apollo types, inputs, resolvers
│   │   ├── jobs/            OrderConfirmationJob
│   │   ├── common/middleware/ LoggingMiddleware
│   │   └── entities/        TypeORM (6 tables)
│   ├── docs/                Postman collection + environment
│   ├── Dockerfile
│   └── .env.example
├── admin-service/
│   ├── app/
│   │   ├── Http/Controllers/Admin/
│   │   │   ├── DashboardController.php
│   │   │   ├── ProductController.php      import + export
│   │   │   ├── ProductImportController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── OrderController.php
│   │   │   ├── UserController.php
│   │   │   └── ReportController.php       CSV + PDF
│   │   ├── Http/Controllers/Api/
│   │   │   └── UserController.php         shared JWT routes
│   │   ├── Http/Middleware/
│   │   │   ├── AdminMiddleware.php
│   │   │   └── JwtMiddleware.php
│   │   ├── Http/Requests/Admin/
│   │   │   ├── StoreProductRequest.php
│   │   │   ├── UpdateProductRequest.php
│   │   │   ├── StoreOrderStatusRequest.php
│   │   │   └── StoreCategoryRequest.php
│   │   ├── Http/Resources/
│   │   │   ├── ProductResource.php
│   │   │   ├── ProductCollection.php
│   │   │   ├── OrderResource.php
│   │   │   └── UserResource.php
│   │   └── Jobs/
│   │       └── ProcessProductImage.php
│   ├── database/migrations/  6 migration files
│   ├── resources/views/admin/
│   ├── routes/web.php + api.php
│   ├── tests/Feature/Admin/  21 tests
│   ├── Dockerfile
│   └── .env.example
├── nginx/nginx.conf
└── docker-compose.yml        7 services
```

---

## Postman

Import from `api-gateway/docs/`:
- `ecommerce-api.postman_collection.json`
- `ecommerce-local.postman_environment.json`

Run **Auth → Login (Admin)** first — token saves automatically.

---

## Environment Variables

### api-gateway/.env.example
```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=ecommerce
DB_USER=ecommerce_user
DB_PASS=secret
REDIS_HOST=localhost
REDIS_PORT=6379
ELASTICSEARCH_URL=http://localhost:9200
JWT_SECRET=your-super-secret-jwt-key-change-in-production
JWT_EXPIRES_IN=15m
PORT=3000
API_KEY=internal-api-key-secret
```

### admin-service/.env.example
```
APP_NAME="Ecommerce Admin"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ecommerce
DB_USERNAME=ecommerce_user
DB_PASSWORD=secret
SESSION_DRIVER=file
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
JWT_SECRET=your-super-secret-jwt-key-change-in-production
NESTJS_API_URL=http://localhost:3000
API_KEY=internal-api-key-secret
```

---

## Future Improvements

The items below are tracked as `TODO` comments directly in the source code. They are collected here as a roadmap for the next iteration.

### 🔐 Security & Authentication

**Refresh token rotation**
The current auth flow issues a single short-lived JWT (15 min) with no way to renew it silently. The next step is a classic refresh-token pair: store an opaque refresh token in Redis with a 7-day TTL, return it as an `HttpOnly` cookie, and rotate it on every use to detect replay attacks.
- `api-gateway/src/auth/auth.service.ts`

**Rate limiting on auth endpoints**
`POST /api/auth/login` and `/register` are currently unprotected against brute-force. Add `@nestjs/throttler` backed by Redis so limits are enforced across every running Nest.js instance (e.g. 10 req/min on login, 5 req/min on register per IP).
- `api-gateway/src/auth/auth.controller.ts`

**Environment variable validation at boot**
`JWT_SECRET` and other required env vars are currently read lazily. A missing value surfaces as a runtime auth failure rather than a hard crash at startup. Add a Joi or Zod schema to `ConfigModule.forRoot({ validationSchema })` so the process exits immediately with a clear message if configuration is incomplete.
- `api-gateway/src/auth/jwt.strategy.ts`

**WebSocket CORS origin allowlist**
The Socket.io gateway is configured with `cors: { origin: '*' }`, which is fine for local development but too permissive for production. Read the allowed origins from an environment variable and pass an array or a validator function.
- `api-gateway/src/websockets/orders.gateway.ts`

---

### 🗄️ Caching

**Switch from in-memory to Redis-backed cache store**
`CacheModule` is currently configured with `store: 'memory'`.  This means the cache is local to each process, is lost on every restart, and cannot be shared across multiple Nest.js instances.  Migrate to `cache-manager-ioredis-yet` (or the official Redis store adapter) so the same cache is visible to every pod in a horizontal deployment.
- `api-gateway/src/app.module.ts`

**Redis `SCAN + DEL` for pattern-based invalidation**
The current list-cache invalidation pre-computes and deletes a fixed set of probable keys (pages 1–5, limits 10 & 20). Once the cache store is Redis, replace this with a single `SCAN 0 MATCH products:*` + `DEL` call so every cached page is reliably cleared regardless of how many combinations exist.
- `api-gateway/src/products/products.service.ts`
- `api-gateway/src/internal/internal.service.ts`

**Cache dashboard aggregate queries**
The admin dashboard runs five full-table `COUNT` / `SUM` queries on every page load.  At high order volumes this will become the slowest path in the admin UI.  Wrap each query in `Cache::remember('dashboard_stats', 60, fn() => ...)` so the expensive SQL runs at most once per minute.
- `admin-service/app/Http/Controllers/Admin/DashboardController.php`

---

### 📨 Queue & Email

**Migrate Nest.js email job to `@nestjs/bullmq`**
`OrderConfirmationJob` is an in-process async call — it shares the Node.js event loop, has no persistence, and cannot be retried if the process crashes mid-send.  Replacing it with a BullMQ queue (backed by Redis) gives persistence, automatic exponential back-off retries, a dead-letter queue, and visibility via Bull Dashboard.
- `api-gateway/src/jobs/order-confirmation.job.ts`

**Real transactional email provider**
The current implementation logs a formatted mock email to stdout.  Integrate a proper provider:
- **AWS SES** — `@aws-sdk/client-ses`, cheapest at scale
- **SendGrid** — `@sendgrid/mail`, generous free tier for lower volumes
- **Nodemailer + SMTP relay** (Mailgun, Postmark) — simplest self-hosted option

Render the email body from a Handlebars or Mjml template so copy and layout can be changed without touching TypeScript.
- `api-gateway/src/jobs/order-confirmation.job.ts`

**Move webhook HTTP calls into a queued job (Laravel)**
Both `ProductController` and `OrderController` call Nest.js synchronously via Guzzle inside the HTTP request cycle. A slow or unavailable Nest.js instance delays the admin response. Dispatch a `NotifyNestjsJob` instead so the webhook is sent asynchronously with retry support, and the admin UI stays snappy.
- `admin-service/app/Http/Controllers/Admin/ProductController.php`
- `admin-service/app/Http/Controllers/Admin/OrderController.php`

---

### 🖼️ Image Processing

**Real image optimisation pipeline**
`ProcessProductImage` currently just reads the file size and logs a message. A production-ready pipeline would:
1. Resize to multiple breakpoints (e.g. 1200 px, 800 px, 400 px, 200 px thumbnail).
2. Convert to **WebP** for modern browsers while keeping a JPEG fallback.
3. Strip EXIF metadata to avoid leaking GPS coordinates.
4. Upload all variants to **S3** (or any object store) and store the CDN URLs rather than local disk paths.
5. Persist each variant in a dedicated `product_images` table so the API can serve the right size based on the client's viewport.

`intervention/image` (v3) is already installed — it just needs to be wired up.
- `admin-service/app/Jobs/ProcessProductImage.php`

---

### 💳 Payments

**Replace mock payment with a real gateway**
The order service calls `console.log` and immediately sets the status to `CONFIRMED`.  The recommended path is Stripe `PaymentIntents`:
1. Create / confirm the intent inside the same DB transaction as the inventory reservation.
2. On payment failure, let the transaction roll back so reserved stock is automatically released.
3. Use Stripe webhooks to handle async outcomes (3DS challenge, bank decline after authorisation, etc.).
- `api-gateway/src/orders/orders.service.ts`

---

### 🔄 Order State Machine

**Enforce legal status transitions**
`PUT /api/orders/:id/status` currently accepts any valid enum value regardless of the current status. This allows nonsensical transitions like `DELIVERED → PENDING` or `CANCELLED → SHIPPED`. A simple transition map (`allowedFrom[current] = [next, ...]`) checked before the update would return a clear `422 Unprocessable Entity` for invalid sequences.
- `api-gateway/src/orders/orders.service.ts`
- `api-gateway/src/orders/orders.controller.ts`

---

### 🔍 Search

**Async Elasticsearch indexing**
`SearchService.indexProduct` is called inline after every product write. If Elasticsearch is temporarily unavailable the update is silently dropped and the index drifts from the database. Dispatching the indexing call as a BullMQ job gives automatic retry and makes the product write path faster by removing the ES network round-trip from the hot path.
- `api-gateway/src/search/search.service.ts`

---

### 🏗️ Architecture & Scalability

**Disable TypeORM `synchronize` in production**
`synchronize: true` automatically alters the live database schema on every Nest.js startup.  In production this can cause accidental column drops or type changes.  Set `synchronize: false` and generate explicit migration files (`typeorm migration:generate`) that are reviewed and applied deliberately.
- `api-gateway/src/app.module.ts`

**Extract `NestjsWebhookService` in Laravel**
Both `ProductController` and `OrderController` instantiate `\GuzzleHttp\Client` inline.  Extracting this into a single injectable service makes the code testable (mock the service in feature tests instead of HTTP), centralises retry / timeout configuration, and keeps controllers thin.
- `admin-service/app/Http/Controllers/Admin/ProductController.php`
- `admin-service/app/Http/Controllers/Admin/OrderController.php`

**Slugify library for product names**
The current slug generator (`name.toLowerCase().replace(/\s+/g, '-')`) does not handle unicode characters, leading/trailing hyphens, or double-hyphens correctly.  Replace with the `slugify` npm package and add a fallback uniqueness suffix only when the slug already exists in the database (rather than always appending a timestamp).
- `api-gateway/src/products/products.service.ts`

**Move to a separate Inventory service**
Inventory checks and reservations are currently embedded inside `OrdersService`.  As order throughput grows this becomes a contention point.  Extracting inventory management into its own NestJS module (or a separate microservice using `@nestjs/microservices` with Redis transport) allows it to be scaled and tested independently, and paves the way for more sophisticated strategies like oversell protection and warehouse-level stock partitioning.
- `api-gateway/src/orders/orders.service.ts`

