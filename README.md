# Ecommerce Platform

Hybrid e-commerce platform built with **Nest.js** (API Gateway) and **Laravel** (Admin Panel), sharing a PostgreSQL database. Features real-time WebSocket updates, GraphQL API alongside REST, Elasticsearch advanced search, JWT authentication shared between both services, Redis caching with webhook-based invalidation, and a full CI/CD pipeline.

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
             +----------+-----------+
                        |
               [PostgreSQL :5432]
               [Redis :6379]
               [Elasticsearch :9200]
```

**Nest.js** — public-facing API:
- JWT authentication (register, login)
- Product browsing with Redis cache
- Elasticsearch advanced search with fuzzy matching, filters, aggregations
- Order placement with inventory management and transactions
- GraphQL endpoint alongside REST
- WebSocket gateway for real-time order updates
- Internal webhook endpoints for cache invalidation

**Laravel** — internal admin panel:
- Session-based authentication (admin only)
- Product and category management with image upload
- Order status management with webhook sync to Nest.js
- User management
- Sales reports with CSV export
- Fires webhooks to Nest.js on every product/order update

Both services share the same PostgreSQL database and the same JWT secret.

---

## Tech Stack

| Layer         | Technology                        |
|---------------|-----------------------------------|
| API           | Nest.js 11 + TypeScript           |
| Admin         | Laravel 13 + Blade + Tailwind     |
| Database      | PostgreSQL 15                     |
| Cache         | Redis 7                           |
| Search        | Elasticsearch 8.13                |
| Search UI     | Kibana 8.13                       |
| Auth          | JWT (shared secret, HS256)        |
| Real-time     | Socket.io WebSockets              |
| GraphQL       | Apollo Server + @nestjs/graphql   |
| Container     | Docker + Docker Compose + Nginx   |
| CI/CD         | GitHub Actions                    |

---

## Quick Start

**Prerequisites:** Docker Desktop, Node.js 22, PHP 8.4, Composer

```bash
# 1. Clone repository
git clone <repo-url>
cd ecommerce-platform

# 2. Start all infrastructure
docker-compose up -d postgres redis elasticsearch

# 3. Setup Nest.js API
cd api-gateway
npm install
cp .env.example .env
npm run start:dev

# In a new terminal — seed the database
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
```

---

## Default Credentials

| Role     | Email                  | Password    | Works in          |
|----------|------------------------|-------------|-------------------|
| Admin    | admin@shop.com         | password123 | API + Admin Panel |
| Customer | maria@example.com      | password123 | API only          |
| Customer | georgi@example.com     | password123 | API only          |

> The seeder generates PHP-compatible bcrypt hashes by replacing the Node.js `$2b$` prefix with PHP's `$2y$` prefix. The algorithms are identical — only the prefix differs. This means users registered via the Nest.js API can log in to the Laravel admin panel without any extra steps, as long as they have the `admin` role.

---

## Services

| Service        | URL                           | Notes                    |
|----------------|-------------------------------|--------------------------|
| Nest.js API    | http://localhost:3000         | REST + GraphQL           |
| GraphQL        | http://localhost:3000/graphql | Apollo Playground        |
| Laravel Admin  | http://localhost:8000/admin   | Session auth             |
| Elasticsearch  | http://localhost:9200         | Search engine            |
| Kibana         | http://localhost:5601         | Elasticsearch UI         |
| PostgreSQL     | localhost:5432                | ecommerce_user / secret  |
| Redis          | localhost:6379                |                          |
| Nginx          | http://localhost:80           | Reverse proxy            |

---

## REST API Endpoints

### Authentication
```
POST /api/auth/register    Register new user, returns JWT
POST /api/auth/login       Login, returns JWT
```

### Products — public
```
GET    /api/products              Paginated list, supports search and category filter
GET    /api/products/:id          Single product with category and inventory
POST   /api/products              Create product — Admin role required
PUT    /api/products/:id          Update product — Admin role required
DELETE /api/products/:id          Soft delete — Admin role required
```

### Search — Elasticsearch powered
```
GET /api/search/products          Advanced search with fuzzy matching and aggregations
GET /api/search/reindex           Reindex all products in Elasticsearch
```

Search query parameters:
```
q            Full-text search across name, description, category (fuzzy)
category_id  Filter by category ID
min_price    Minimum price filter
max_price    Maximum price filter
page         Page number (default: 1)
limit        Results per page (default: 10)
```

Search response includes:
```json
{
  "data": [
    {
      "id": 1,
      "name": "iPhone 15",
      "score": 4.24,
      "highlight": { "name": ["<mark>iPhone</mark> 15"] }
    }
  ],
  "meta": { "total": 2, "page": 1, "limit": 10, "last_page": 1 },
  "aggregations": {
    "price_stats": { "min": 849.99, "max": 999.99, "avg": 924.99 },
    "categories": [{ "key": "Phones", "doc_count": 2 }]
  }
}
```

### Orders — JWT required
```
POST /api/orders                  Place order, checks inventory, mock payment
GET  /api/orders/my-orders        Current user's orders
GET  /api/orders/:id              Single order
GET  /api/orders                  All orders — Admin role required
PUT  /api/orders/:id/status       Update status — Admin role required
```

### Internal — X-API-Key required
```
POST /api/internal/cache/invalidate       Called by Laravel after product update
POST /api/internal/orders/:id/status     Called by Laravel after order status update
```

### Shared JWT — Laravel API routes
```
GET /api/me              Returns current user profile (validates Nest.js JWT)
GET /api/user/orders     Returns current user's orders (validates Nest.js JWT)
```

---

## GraphQL API

Available at **http://localhost:3000/graphql** with Apollo Playground.

Add header for authenticated queries:
```json
{ "Authorization": "Bearer your-jwt-token" }
```

### Example Queries

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

### Example Mutations

```graphql
# Admin only
mutation {
  createProduct(input: {
    name: "New Product"
    description: "Description"
    price: 99.99
    category_id: 1
    quantity: 50
  }) {
    id
    name
    price
  }
}
```

---

## WebSockets

Connect to `ws://localhost:3000/orders` using Socket.io.

```javascript
import { io } from 'socket.io-client';

const socket = io('http://localhost:3000/orders', {
  auth: { token: 'your-jwt-token' }
});

socket.on('connected',     (data) => console.log(data.message));
socket.on('order_updated', (data) => console.log(`Order #${data.orderId} is now ${data.status}`));
socket.on('order_created', (data) => console.log(`New order #${data.orderId} placed`));

socket.emit('ping');
socket.on('pong', (data) => console.log(data.timestamp));
```

Events fire automatically when:
- A user places an order → `order_created` broadcast + `order_updated` to the user
- An admin updates order status → `order_updated` to the order owner

---

## Elasticsearch Search

### Features
- **Full-text search** across product name, description, and category
- **Fuzzy matching** — tolerates typos (AUTO fuzziness)
- **Boosted fields** — name matches score 3x higher than description
- **Price range filter** — min_price and max_price parameters
- **Category filter** — exact match by category ID
- **Result highlighting** — matched terms wrapped in `<mark>` tags
- **Aggregations** — price statistics and category breakdown per query
- **Bulk reindex** — reindex all products via GET /api/search/reindex

### Example Searches

```bash
# Full-text fuzzy search
curl "http://localhost:3000/api/search/products?q=iphone"

# With price range
curl "http://localhost:3000/api/search/products?q=laptop&min_price=1000&max_price=3000"

# Category filter
curl "http://localhost:3000/api/search/products?category_id=4"

# Combined
curl "http://localhost:3000/api/search/products?q=pro&category_id=5&min_price=500"

# Reindex after bulk changes
curl "http://localhost:3000/api/search/reindex"
```

### Auto-indexing
Products are automatically indexed in Elasticsearch when:
- A new product is created via POST /api/products
- A product is updated via PUT /api/products/:id
- A product is deleted (removed from index) via DELETE /api/products/:id

---

## Admin Panel

Available at **http://localhost:8000/admin** — admin role required.

- **Dashboard** — revenue, order counts, recent activity
- **Products** — CRUD with image upload, soft deletes, webhooks to invalidate Redis + Elasticsearch
- **Categories** — nested categories, delete blocked if products exist
- **Orders** — status management, webhook sync to Nest.js triggers WebSocket push
- **Users** — customer list with order history
- **Reports** — daily/monthly sales, top products, CSV export

---

## Integration Flow

### Product Update

```
Admin saves product (Laravel)
  -> UPDATE products in PostgreSQL
  -> POST /api/internal/cache/invalidate (Nest.js)
  -> Redis cache cleared for that product
  -> Next API read fetches fresh data from PostgreSQL
  Note: run /api/search/reindex to sync Elasticsearch after bulk changes
```

### Order Status Update

```
Admin changes order status (Laravel)
  -> UPDATE orders in PostgreSQL
  -> POST /api/internal/orders/:id/status (Nest.js)
  -> Nest.js syncs its record
  -> WebSocket push to customer's connected socket
  -> Customer sees instant status update
```

---

## Testing

### Nest.js Unit Tests — 14 passing

```bash
cd api-gateway
npm run test
```

Covers ProductsService and OrdersService — findAll, findOne, create, update, delete, inventory management.

### Laravel Feature Tests — 21 passing

```bash
cd admin-service
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=ecommerce \
DB_USERNAME=ecommerce_user DB_PASSWORD=secret \
php artisan test
```

Covers DashboardTest, ProductTest, OrderTest, ReportTest.

---

## Database Seeding

```bash
cd api-gateway
npm run seed
```

Creates 3 users, 5 categories (2 nested), 5 products with inventory, 2 sample orders.

Reset and reseed:

```bash
docker exec -it ecommerce-platform-postgres-1 psql \
  -U ecommerce_user -d ecommerce \
  -c "TRUNCATE order_items, orders, inventory, products, categories, users RESTART IDENTITY CASCADE;"
npm run seed
curl http://localhost:3000/api/search/reindex
```

---

## CI/CD Pipeline

### ci.yml — every push and PR to main/develop

```
nestjs  -> npm ci, 14 unit tests, TypeScript build
laravel -> composer install, 21 feature tests
docker  -> build both images, validate docker-compose config
```

### cd.yml — push to main only

```
Build production assets for both services
Tag Docker images with git SHA
Ready for deployment
```

---

## Key Design Decisions

**Shared database** — Both services share PostgreSQL. Avoids distributed transaction complexity while allowing independent deployment.

**Redis + Elasticsearch dual layer** — Redis caches exact product lookups (O(1), milliseconds). Elasticsearch powers full-text search with fuzzy matching and aggregations. Different tools for different access patterns.

**Event-driven cache invalidation** — Laravel fires webhooks to Nest.js on every product change. Redis cache is cleared immediately. No stale data.

**Shared JWT secret** — One JWT_SECRET in both .env files. Tokens from Nest.js work in Laravel /api/* routes and vice versa.

**bcrypt prefix compatibility** — Seeder replaces Node.js $2b$ with PHP's $2y$. Identical algorithm, different prefix. Users work in both systems without extra steps.

**Optimistic inventory reservation** — Stock reserved on order, deducted on ship, released on cancel. Prevents overselling without pessimistic locks on reads.

**Soft deletes** — Products never hard-deleted. Order history always references valid products.

**GraphQL alongside REST** — Same service layer, two interfaces. Clients choose based on their needs. No business logic duplication.

---

## Project Structure

```
ecommerce-platform/
├── .github/workflows/
│   ├── ci.yml
│   └── cd.yml
├── api-gateway/
│   ├── src/
│   │   ├── auth/            JWT, guards, decorators, GQL guards
│   │   ├── products/        CRUD, Redis cache
│   │   ├── orders/          Transactions, inventory, WebSocket notify
│   │   ├── internal/        Webhook endpoints
│   │   ├── websockets/      Socket.io real-time gateway
│   │   ├── search/          Elasticsearch service + controller
│   │   ├── graphql/         Apollo types, inputs, resolvers
│   │   └── entities/        TypeORM entities
│   ├── docs/                Postman collection + environment
│   ├── Dockerfile
│   └── .env.example
├── admin-service/
│   ├── app/Http/Controllers/Admin/
│   ├── resources/views/admin/
│   ├── routes/web.php + api.php
│   ├── tests/Feature/Admin/
│   ├── Dockerfile
│   └── .env.example
├── nginx/nginx.conf
└── docker-compose.yml
```

---

## Bonus Features Implemented

All 5 bonus points from the requirements:

1. **WebSockets** — Real-time order updates via Socket.io. Users receive instant push notifications when their order status changes.
2. **GraphQL** — Full Apollo GraphQL endpoint alongside REST. Supports queries for products and orders, mutations for admin operations, JWT auth via custom GQL guards.
3. **Elasticsearch** — Advanced product search with fuzzy matching, field boosting, price range filters, category filters, result highlighting, and price/category aggregations per query.
4. **Docker Compose** — All services defined: PostgreSQL, Redis, Elasticsearch, Kibana, Nest.js, Laravel, Nginx.
5. **CI/CD** — GitHub Actions with separate CI (tests + build) and CD (production build + Docker image tagging) workflows.

---

## Postman Collection

Import from `api-gateway/docs/`:
- `ecommerce-api.postman_collection.json`
- `ecommerce-local.postman_environment.json`

Select **Ecommerce Local** environment. Run **Auth → Login (Admin)** first — token saves automatically.

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
