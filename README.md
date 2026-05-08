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
[PostgreSQL] ←────────────────── [Laravel Admin :8000]
                                    - Session auth (admin only)
                                    - Product/Category CRUD
                                    - Image upload + queue processing
                                    - Order management
                                    - Reports (CSV + PDF)
                                    - Fires webhooks → Nest.js
                                         on product/order change
```

### Service Communication

```
When admin saves a product (Laravel):
  1. UPDATE products in PostgreSQL
  2. POST /api/internal/cache/invalidate → Nest.js clears Redis
  3. ProcessProductImage job dispatched → queue worker processes image

When admin updates order status (Laravel):
  1. UPDATE orders in PostgreSQL
  2. POST /api/internal/orders/:id/status → Nest.js syncs record
  3. Nest.js pushes WebSocket event to customer

When customer places order (Nest.js):
  1. Transaction: check inventory, create order, reserve stock
  2. Mock payment processing
  3. OrderConfirmationJob dispatched → queue sends email (mock)
  4. WebSocket push to customer + broadcast to admin
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
| Queue (API)   | In-process async jobs (Node.js)        |
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

> **Note:** The seeder generates PHP-compatible bcrypt hashes by replacing the Node.js `$2b$` prefix with PHP's `$2y$` prefix. The algorithms are identical — only the prefix differs. Users created via the Nest.js API can log in to the Laravel admin panel without any extra steps (as long as they have the `admin` role).

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

### Products — public
```
GET    /api/products              Paginated list, search and category filter
GET    /api/products/:id          Single product with category and inventory
POST   /api/products              Create — Admin only
PUT    /api/products/:id          Update — Admin only
DELETE /api/products/:id          Soft delete — Admin only
```

### Search — Elasticsearch powered
```
GET /api/search/products    Full-text search with filters and aggregations
GET /api/search/reindex     Reindex all products
```

Query parameters:
```
q            Full-text search (fuzzy, boosted fields)
category_id  Filter by category
min_price    Minimum price
max_price    Maximum price
page         Page number (default 1)
limit        Per page (default 10)
```

### Orders — JWT required
```
POST /api/orders              Place order — checks inventory, mock payment, sends email
GET  /api/orders/my-orders    Current user's orders
GET  /api/orders/:id          Single order
GET  /api/orders              All orders — Admin only
PUT  /api/orders/:id/status   Update status — Admin only
```

### Internal — X-API-Key required
```
POST /api/internal/cache/invalidate       Invalidate Redis cache for a product
POST /api/internal/orders/:id/status     Sync order status from Laravel
```

### Shared JWT — Laravel API routes
```
GET /api/me              Current user profile (validates Nest.js JWT in Laravel)
GET /api/user/orders     Current user's orders (validates Nest.js JWT in Laravel)
```

---

## GraphQL API

Available at **http://localhost:3000/graphql** with Apollo Playground.

Add to Headers for authenticated queries:
```json
{ "Authorization": "Bearer your-jwt-token" }
```

### Queries

```graphql
# Public
query {
  products(filter: { page: 1, limit: 10, search: "iPhone" }) {
    total
    page
    last_page
    data {
      id
      name
      price
      category { name }
      inventory { quantity }
    }
  }
}

query {
  product(id: 1) {
    id
    name
    price
    description
  }
}

# Authenticated
query {
  myOrders {
    id
    status
    total_amount
    items { quantity unit_price subtotal }
  }
}

# Admin only
query {
  allOrders {
    id
    status
    total_amount
    created_at
  }
}
```

### Mutations (Admin only)

```graphql
mutation {
  createProduct(input: {
    name: "New Product"
    description: "Description here"
    price: 99.99
    category_id: 1
    quantity: 50
  }) {
    id
    name
    price
  }
}

mutation {
  deleteProduct(id: 5) {
    id
    name
  }
}
```

---

## WebSockets

Connect to `ws://localhost:3000/orders` using Socket.io:

```javascript
import { io } from 'socket.io-client';

const socket = io('http://localhost:3000/orders', {
  auth: { token: 'your-jwt-token' }
});

socket.on('connected',     (data) => console.log(data.message));
socket.on('order_updated', (data) => console.log(`Order #${data.orderId} → ${data.status}`));
socket.on('order_created', (data) => console.log(`New order #${data.orderId}`));
socket.emit('ping');
socket.on('pong',          (data) => console.log(data.timestamp));
```

Events fire automatically:
- Order placed → `order_created` broadcast + `order_updated` to the user
- Order status updated by admin → `order_updated` to the order owner

---

## Elasticsearch Search

```bash
# Full-text fuzzy search
curl "http://localhost:3000/api/search/products?q=iphone"

# Price range filter
curl "http://localhost:3000/api/search/products?q=laptop&min_price=1000&max_price=3000"

# Category filter
curl "http://localhost:3000/api/search/products?category_id=4"

# Reindex after bulk changes
curl "http://localhost:3000/api/search/reindex"
```

Response includes highlights, relevance scores, price stats aggregation, and category breakdown.

---

## Queue Jobs

### Nest.js — OrderConfirmationJob
Dispatched asynchronously after every successful order. Sends a mock confirmation email with order details. Non-blocking — HTTP response returns before email is processed.

```
[EmailQueue] Processing order confirmation for order #3
====================================
  ORDER CONFIRMATION EMAIL (MOCK)
  To:      maria@example.com
  Subject: Order #3 Confirmed!
  Items:   iPhone 15 x1 @ $999.99
  Total:   $999.99
====================================
```

### Laravel — ProcessProductImage
Dispatched when a product image is uploaded (create or update). Processes the image asynchronously via the queue worker. Includes retry logic (3 attempts) and failure handling.

```bash
# Start the worker
DB_USERNAME=ecommerce_user DB_PASSWORD=secret php artisan queue:work --verbose
```

---

## Admin Panel

Available at **http://localhost:8000/admin**

### Dashboard
Revenue totals, order counts, pending orders, recent activity table.

### Products
- CRUD with Form Request validation (StoreProductRequest, UpdateProductRequest)
- Image upload dispatches ProcessProductImage queue job
- Soft delete with restore
- Search by name
- **Bulk CSV import** with row-level error reporting and template download
- Webhook to Nest.js on every save (invalidates Redis cache)

### Categories
- Nested categories (parent/child)
- StoreCategoryRequest validation
- Delete blocked if active products or subcategories exist

### Orders
- Filterable list by status and customer email
- StoreOrderStatusRequest validation
- Status update fires webhook to Nest.js (triggers WebSocket push to customer)
- Order detail with line items

### Users
- Customer list with roles
- Per-user order history

### Reports
- Daily, weekly, monthly sales data
- Top 10 products by revenue
- **CSV export** (daily, monthly)
- **PDF export** with stats summary, sales table, top products (DomPDF)

---

## Testing

### Nest.js Unit Tests — 14 passing

```bash
cd api-gateway
npm run test
```

Covers ProductsService (findAll with filters, findOne, create with inventory, soft delete) and OrdersService (findOne, findAll, updateStatus with inventory release).

### Laravel Feature Tests — 21 passing

```bash
cd admin-service
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=ecommerce \
DB_USERNAME=ecommerce_user DB_PASSWORD=secret \
php artisan test
```

Covers DashboardTest, ProductTest (CRUD, image upload, validation), OrderTest (list, filter, status update), ReportTest (view, CSV/PDF export).

---

## Database Seeding

```bash
cd api-gateway
npm run seed
```

Creates 3 users (1 admin, 2 customers), 5 categories (3 top-level, 2 nested under Electronics), 5 products with inventory, 2 sample orders with items.

Reset and reseed:

```bash
docker exec -it ecommerce-platform-postgres-1 psql \
  -U ecommerce_user -d ecommerce \
  -c "TRUNCATE order_items, orders, inventory, products, categories, users RESTART IDENTITY CASCADE;"
cd api-gateway
npm run seed
curl http://localhost:3000/api/search/reindex
```

---

## CI/CD Pipeline

### ci.yml — every push and PR to main/develop

```
nestjs job:   npm ci → 14 unit tests → TypeScript build
laravel job:  composer install → 21 feature tests
docker job:   build both images → validate docker-compose config
```

### cd.yml — push to main only

```
Build production assets (both services)
Tag Docker images with git SHA
Ready for deployment
```

---

## Key Design Decisions

**Shared database**
Both services share PostgreSQL. Avoids distributed transaction complexity while allowing independent deployment. Laravel reads directly from the DB for accurate admin data; Nest.js uses Redis as a read-through cache.

**Redis caching with webhook invalidation**
Product reads cached in Redis (5-minute TTL). When Laravel updates a product, it calls `POST /api/internal/cache/invalidate`. Cache is cleared immediately — no stale data.

**Queue-based job processing**
Both services use queues for non-blocking operations. Nest.js dispatches email jobs in-process (async/await). Laravel uses Redis-backed queues for image processing with retry logic (3 attempts) and failure logging.

**JWT shared secret**
One `JWT_SECRET` in both `.env` files. Tokens issued by Nest.js are valid in Laravel `/api/*` routes via `JwtMiddleware`. Admin panel uses separate session-based auth.

**bcrypt prefix compatibility**
Node.js bcryptjs generates `$2b$` hashes. PHP's bcrypt generates `$2y$`. Identical algorithm, different prefix. The seeder replaces `$2b$` → `$2y$` so users work in both systems without manual steps.

**Form Request classes**
All Laravel admin forms use dedicated Form Request classes (`StoreProductRequest`, `UpdateProductRequest`, `StoreOrderStatusRequest`, `StoreCategoryRequest`) with custom validation messages and role-based authorization.

**Laravel Resources**
API responses from Laravel's `/api/*` routes are transformed through Resource classes (`ProductResource`, `OrderResource`, `UserResource`) ensuring consistent structure, proper type casting, and no sensitive field leakage.

**Optimistic inventory reservation**
Stock moved to `reserved` on order, deducted on ship, released on cancel. Prevents overselling without locking reads.

**Soft deletes**
Products never hard-deleted. Order history always references valid products.

**GraphQL alongside REST**
Same service layer, two interfaces. Reuses all business logic, guards, and caching. Custom `GqlAuthGuard` and `GqlRolesGuard` extract the request from GraphQL context.

**Elasticsearch dual-layer search**
Redis for exact lookups (O(1), sub-millisecond). Elasticsearch for full-text search with fuzzy matching, field boosting (name^3), price range filters, category filters, result highlighting, and aggregations.

---

## Implemented Requirements

### Core
| Requirement | Status |
|-------------|--------|
| Database schema with relationships | ✅ |
| Nest.js + TypeORM + JWT | ✅ |
| class-validator DTOs | ✅ |
| Role-based guards (Customer, Admin) | ✅ |
| Global error handling | ✅ |
| Products CRUD + pagination + filters | ✅ |
| Redis caching | ✅ |
| Request logging middleware | ✅ |
| Unit tests | ✅ 14 passing |
| Order creation + inventory check | ✅ |
| Mock payment | ✅ |
| Email queue job | ✅ |
| Inventory update | ✅ |
| Laravel Breeze auth + admin middleware | ✅ |
| Dashboard with metrics | ✅ |
| Product CRUD + image upload | ✅ |
| Nested categories | ✅ |
| Bulk CSV import with template | ✅ |
| Soft deletes + restore | ✅ |
| Laravel Resources | ✅ |
| Form Request classes | ✅ |
| Image upload queue job | ✅ |
| Order listing + filters | ✅ |
| Order status management | ✅ |
| Sales reports (daily/weekly/monthly) | ✅ |
| CSV export | ✅ |
| PDF export | ✅ |
| Service communication via webhooks | ✅ |
| API key authentication | ✅ |
| Shared JWT | ✅ |
| Docker + Nginx | ✅ |
| DB seeders | ✅ |

### Bonus (5/5)
| Bonus | Status |
|-------|--------|
| WebSockets (real-time order updates) | ✅ |
| GraphQL endpoint alongside REST | ✅ |
| Elasticsearch advanced search | ✅ |
| Docker Compose all services | ✅ |
| CI/CD pipeline | ✅ |

---

## Project Structure

```
ecommerce-platform/
├── .github/workflows/
│   ├── ci.yml                       Tests + Docker build on every push
│   └── cd.yml                       Production build + image tagging on main
│
├── api-gateway/                     Nest.js public API
│   ├── src/
│   │   ├── auth/
│   │   │   ├── guards/              JwtAuthGuard, RolesGuard, ApiKeyGuard
│   │   │   │                        GqlAuthGuard, GqlRolesGuard
│   │   │   └── dto/                 RegisterDto, LoginDto
│   │   ├── products/
│   │   │   ├── dto/                 CreateProductDto, UpdateProductDto, QueryDto
│   │   │   └── products.service.ts  CRUD with Redis cache invalidation
│   │   ├── orders/
│   │   │   ├── dto/                 CreateOrderDto, OrderItemDto
│   │   │   └── orders.service.ts    Transactions, inventory, email job, WebSocket
│   │   ├── internal/                Webhook endpoints from Laravel
│   │   ├── websockets/              Socket.io gateway (order updates)
│   │   ├── search/                  Elasticsearch service + controller
│   │   ├── graphql/
│   │   │   ├── types/               ProductType, OrderType, CategoryType
│   │   │   ├── inputs/              ProductsFilterInput, CreateProductInput
│   │   │   └── resolvers/           ProductsResolver, OrdersResolver
│   │   ├── jobs/
│   │   │   └── order-confirmation.job.ts   Mock email queue job
│   │   ├── common/
│   │   │   └── middleware/
│   │   │       └── logging.middleware.ts   HTTP request logger
│   │   └── entities/                TypeORM entities (6 tables)
│   ├── docs/                        Postman collection + environment
│   ├── Dockerfile
│   └── .env.example
│
├── admin-service/                   Laravel admin panel
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Admin/
│   │   │   │   │   ├── DashboardController.php
│   │   │   │   │   ├── ProductController.php       Form Requests + Queue jobs
│   │   │   │   │   ├── ProductImportController.php CSV import + template
│   │   │   │   │   ├── CategoryController.php      Form Requests
│   │   │   │   │   ├── OrderController.php         Form Requests + Webhooks
│   │   │   │   │   ├── UserController.php
│   │   │   │   │   └── ReportController.php        CSV + PDF export
│   │   │   │   └── Api/
│   │   │   │       └── UserController.php          Resources + JWT routes
│   │   │   ├── Middleware/
│   │   │   │   ├── AdminMiddleware.php
│   │   │   │   └── JwtMiddleware.php
│   │   │   └── Requests/Admin/
│   │   │       ├── StoreProductRequest.php
│   │   │       ├── UpdateProductRequest.php
│   │   │       ├── StoreOrderStatusRequest.php
│   │   │       └── StoreCategoryRequest.php
│   │   ├── Jobs/
│   │   │   └── ProcessProductImage.php    Queue job (3 retries, failure log)
│   │   ├── Http/Resources/
│   │   │   ├── ProductResource.php
│   │   │   ├── ProductCollection.php
│   │   │   ├── OrderResource.php
│   │   │   └── UserResource.php
│   │   └── Models/
│   │       └── User.php
│   ├── resources/views/admin/
│   │   ├── dashboard.blade.php
│   │   ├── products/ (index, create, edit, import)
│   │   ├── categories/ (index, create, edit)
│   │   ├── orders/ (index, show)
│   │   ├── users/ (index, show)
│   │   └── reports/ (index, pdf/sales)
│   ├── routes/
│   │   ├── web.php                  Admin panel + import routes
│   │   └── api.php                  JWT-protected API routes
│   ├── tests/Feature/Admin/         21 feature tests
│   ├── Dockerfile
│   └── .env.example
│
├── nginx/nginx.conf                 Reverse proxy
└── docker-compose.yml               All 7 services
```

---

## Postman Collection

Import from `api-gateway/docs/`:
- `ecommerce-api.postman_collection.json`
- `ecommerce-local.postman_environment.json`

Select **Ecommerce Local** environment. Run **Auth → Login (Admin)** first — JWT token saves automatically to all requests.

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
