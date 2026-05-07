# Ecommerce Platform

Hybrid e-commerce platform built with **Nest.js** (API Gateway) and **Laravel** (Admin Panel), sharing a PostgreSQL database. Features real-time WebSocket updates, GraphQL API alongside REST, JWT authentication shared between both services, Redis caching with webhook-based invalidation, and a full CI/CD pipeline.

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
```

**Nest.js** — public-facing API used by mobile apps and external clients:
- JWT authentication (register, login)
- Product browsing with Redis cache
- Order placement with inventory management
- GraphQL endpoint alongside REST
- WebSocket gateway for real-time order updates
- Internal webhook endpoints for cache invalidation

**Laravel** — internal admin panel used by staff:
- Session-based authentication (admin only)
- Product and category management with image upload
- Order status management with webhook sync to Nest.js
- User management
- Sales reports with CSV export
- Fires webhooks to Nest.js on every product/order update

Both services share the same PostgreSQL database and the same JWT secret, enabling a unified authentication experience.

---

## Tech Stack

| Layer      | Technology                        |
|------------|-----------------------------------|
| API        | Nest.js 11 + TypeScript           |
| Admin      | Laravel 13 + Blade + Tailwind     |
| Database   | PostgreSQL 15                     |
| Cache      | Redis 7                           |
| Auth       | JWT (shared secret, HS256)        |
| Real-time  | Socket.io WebSockets              |
| GraphQL    | Apollo Server + @nestjs/graphql   |
| Container  | Docker + Docker Compose + Nginx   |
| CI/CD      | GitHub Actions                    |

---

## Quick Start

**Prerequisites:** Docker Desktop, Node.js 22, PHP 8.4, Composer

```bash
# 1. Clone repository
git clone <repo-url>
cd ecommerce-platform

# 2. Start infrastructure
docker-compose up -d postgres redis

# 3. Setup Nest.js API
cd api-gateway
npm install
cp .env.example .env
npm run start:dev

# In a new terminal — seed the database
cd api-gateway
npm run seed

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

| Service        | URL                        | Notes                  |
|----------------|----------------------------|------------------------|
| Nest.js API    | http://localhost:3000      | REST + GraphQL         |
| GraphQL        | http://localhost:3000/graphql | Apollo Playground   |
| Laravel Admin  | http://localhost:8000/admin | Session auth          |
| PostgreSQL     | localhost:5432             | ecommerce_user / secret |
| Redis          | localhost:6379             |                        |
| Nginx          | http://localhost:80        | Reverse proxy          |

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

### Orders — JWT required
```
POST /api/orders                  Place order, checks inventory, mock payment
GET  /api/orders/my-orders        Current user's orders
GET  /api/orders/:id              Single order (users see own, admin sees all)
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

### Queries

```graphql
# Public — no auth required
query {
  products(filter: { page: 1, limit: 10, search: "iPhone", category_id: 1 }) {
    total
    page
    last_page
    data {
      id
      name
      price
      description
      is_active
      category {
        id
        name
      }
      inventory {
        quantity
        reserved
      }
    }
  }
}

query {
  product(id: 1) {
    id
    name
    price
    description
    category { name }
    inventory { quantity }
  }
}

# Authenticated — add Authorization: Bearer <token> header
query {
  myOrders {
    id
    status
    total_amount
    created_at
    items {
      product_id
      quantity
      unit_price
      subtotal
    }
  }
}

query {
  order(id: 1) {
    id
    status
    total_amount
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

### Mutations

```graphql
# Admin only
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

Connect to `ws://localhost:3000/orders` using Socket.io.

```javascript
import { io } from 'socket.io-client';

const socket = io('http://localhost:3000/orders', {
  auth: { token: 'your-jwt-token' }
});

// Fired when connection is established
socket.on('connected', (data) => {
  console.log(data.message); // "Connected to order updates"
});

// Fired when your order status changes
socket.on('order_updated', (data) => {
  console.log(`Order #${data.orderId} is now ${data.status}`);
  console.log(data.message);
});

// Fired when any new order is placed (broadcast to all)
socket.on('order_created', (data) => {
  console.log(`New order #${data.orderId} placed`);
});

// Ping-pong health check
socket.emit('ping');
socket.on('pong', (data) => console.log(data.timestamp));
```

Events are emitted automatically when:
- A user places an order → `order_created` broadcast + `order_updated` to the user
- An admin updates order status → `order_updated` to the order owner

---

## Admin Panel

Available at **http://localhost:8000/admin** — admin role required.

### Dashboard
- Total orders, total revenue, total products, pending orders count
- Recent orders table with status indicators

### Products
- Full CRUD with image upload (max 10MB, stored in local storage)
- Soft delete with restore functionality
- Search by name
- Paginated list with category and stock info
- On save, fires webhook to Nest.js to invalidate Redis cache

### Categories
- Create, edit, delete categories
- Nested support — categories can have parent categories
- Delete is blocked if the category has active products or subcategories

### Orders
- Filterable list by status and customer email
- Status management: pending, confirmed, processing, shipped, delivered, cancelled
- Order detail view with line items and totals
- Status update fires webhook to Nest.js, triggering WebSocket push to customer

### Users
- Customer list with role badges
- Per-user order history

### Reports
- Daily sales (last 7 days)
- Monthly sales (last 12 months)
- Top 10 products by revenue
- CSV export for daily and monthly data

---

## Integration Flow

### Product Update (Laravel → Nest.js cache invalidation)

```
Admin edits product in Laravel
  → PUT /admin/products/:id
  → UPDATE products SET ... in PostgreSQL
  → POST /api/internal/cache/invalidate {"type":"product","id":N}
  → Nest.js deletes product_N and products_list from Redis
  → Next GET /api/products/:id fetches fresh data from PostgreSQL
```

### Order Status Update (Laravel → Nest.js → WebSocket → Customer)

```
Admin changes order status in Laravel
  → PUT /admin/orders/:id {"status":"shipped"}
  → UPDATE orders SET status='shipped' in PostgreSQL
  → POST /api/internal/orders/:id/status
  → Nest.js updates its own order record
  → WebSocket push to user_N room
  → Customer's mobile app receives order_updated event instantly
```

---

## Testing

### Nest.js Unit Tests — 14 passing

```bash
cd api-gateway
npm run test
```

Covers:
- `ProductsService` — findAll with filters, findOne, create with inventory, soft delete
- `OrdersService` — findOne, findAll pagination, updateStatus with inventory release

### Laravel Feature Tests — 21 passing

```bash
cd admin-service
DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=ecommerce \
DB_USERNAME=ecommerce_user DB_PASSWORD=secret \
php artisan test
```

Covers:
- `DashboardTest` — auth required, admin role required, metrics render
- `ProductTest` — CRUD, validation, image upload, soft delete, guest access denied
- `OrderTest` — list, filter, detail, status update, validation
- `ReportTest` — view, CSV export daily/monthly, correct headers

---

## Database Seeding

```bash
cd api-gateway
npm run seed
```

Creates:
- 3 users (1 admin, 2 customers)
- 3 top-level categories + 2 subcategories under Electronics
- 5 products with inventory
- 2 sample orders for maria@example.com

To reset and reseed:

```bash
docker exec -it ecommerce-platform-postgres-1 psql \
  -U ecommerce_user -d ecommerce \
  -c "TRUNCATE order_items, orders, inventory, products, categories, users RESTART IDENTITY CASCADE;"

npm run seed
```

---

## CI/CD Pipeline

Two GitHub Actions workflows in `.github/workflows/`:

### ci.yml — runs on every push and pull request to main/develop

```
nestjs job:
  - Install Node.js 22
  - npm ci
  - Run 14 unit tests
  - Build TypeScript

laravel job:
  - Install PHP 8.4
  - composer install
  - Run 21 feature tests

docker job (after tests pass):
  - Build Nest.js Docker image
  - Build Laravel Docker image
  - Validate docker-compose config
```

### cd.yml — runs on push to main only

```
deploy job:
  - Build production Nest.js assets
  - Build production Laravel assets
  - Tag Docker images with git SHA
  - Ready for deployment to any environment
```

---

## Key Design Decisions

**Shared database over microservice isolation**
Both services read and write to the same PostgreSQL instance. This avoids distributed transaction complexity while still allowing independent deployment and scaling. For the current scale, the simplicity benefit outweighs the coupling cost.

**Redis caching with event-driven invalidation**
Product reads are cached in Redis with a 5-minute TTL. When Laravel admin updates a product, it fires a POST webhook to Nest.js `/api/internal/cache/invalidate`. This ensures the mobile API returns fresh data without waiting for TTL expiry — typically within milliseconds of the admin save.

**JWT shared secret for unified auth**
A single `JWT_SECRET` environment variable is read by both services. Tokens issued by Nest.js are valid in Laravel's `/api/*` routes via the `JwtMiddleware`. The Laravel admin panel uses session-based auth separately, but also validates JWT tokens for API routes.

**bcrypt prefix compatibility**
Node.js bcryptjs generates `$2b$` hashes. PHP's password_hash generates `$2y$` hashes. The algorithms are identical — only the prefix differs. The seeder replaces `$2b$` with `$2y$` so users created via the API can log in to the Laravel admin panel without any manual intervention.

**Optimistic inventory reservation**
When an order is placed, stock is moved to `reserved` (not deducted from `quantity`). Actual deduction happens when the order status changes to `shipped`. Cancellations release the reservation. This prevents overselling without requiring pessimistic locking on reads.

**Soft deletes on products**
Products are never hard-deleted. The `deleted_at` column is set instead. This preserves order history integrity — order items always reference a valid product even after the product is removed from the catalog. Laravel admin shows a restore option for soft-deleted products.

**CQRS-inspired separation**
Write operations (create order, update product) go through Nest.js with full validation, transactions, and cache invalidation. Reads use Redis as the first layer, falling back to PostgreSQL on cache miss. The Laravel admin bypasses the cache and reads directly from PostgreSQL to always show accurate data.

**GraphQL alongside REST**
The GraphQL endpoint provides an alternative interface for the same data, useful for clients that need flexible queries. It reuses the same service layer as REST — no duplication of business logic. Both REST and GraphQL share the same JWT authentication guards.

---

## Project Structure

```
ecommerce-platform/
├── .github/
│   └── workflows/
│       ├── ci.yml              GitHub Actions CI
│       └── cd.yml              GitHub Actions CD
├── api-gateway/                Nest.js public API
│   ├── src/
│   │   ├── auth/               JWT strategy, guards, decorators
│   │   │   ├── guards/         JwtAuthGuard, RolesGuard, ApiKeyGuard
│   │   │   │                   GqlAuthGuard, GqlRolesGuard
│   │   │   └── dto/            RegisterDto, LoginDto
│   │   ├── products/           CRUD with Redis caching
│   │   │   ├── dto/            CreateProductDto, UpdateProductDto, QueryDto
│   │   │   └── products.service.ts
│   │   ├── orders/             Order processing with transactions
│   │   │   ├── dto/            CreateOrderDto, OrderItemDto
│   │   │   └── orders.service.ts
│   │   ├── internal/           Webhook endpoints from Laravel
│   │   ├── websockets/         Socket.io gateway for real-time updates
│   │   ├── graphql/            Apollo GraphQL
│   │   │   ├── types/          ProductType, OrderType, CategoryType
│   │   │   ├── inputs/         ProductsFilterInput, CreateProductInput
│   │   │   └── resolvers/      ProductsResolver, OrdersResolver
│   │   ├── entities/           TypeORM entities
│   │   │   ├── user.entity.ts
│   │   │   ├── category.entity.ts
│   │   │   ├── product.entity.ts
│   │   │   ├── inventory.entity.ts
│   │   │   ├── order.entity.ts
│   │   │   └── order-item.entity.ts
│   │   └── database/
│   │       └── seed.ts         Database seeder
│   ├── test/                   Unit tests (14 tests)
│   ├── docs/
│   │   ├── ecommerce-api.postman_collection.json
│   │   └── ecommerce-local.postman_environment.json
│   ├── Dockerfile
│   └── .env.example
├── admin-service/              Laravel admin panel
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Admin/
│   │   │   │   │   ├── DashboardController.php
│   │   │   │   │   ├── ProductController.php
│   │   │   │   │   ├── CategoryController.php
│   │   │   │   │   ├── OrderController.php
│   │   │   │   │   ├── UserController.php
│   │   │   │   │   └── ReportController.php
│   │   │   │   └── Api/
│   │   │   │       └── UserController.php   Shared JWT routes
│   │   │   └── Middleware/
│   │   │       ├── AdminMiddleware.php
│   │   │       └── JwtMiddleware.php
│   │   └── Models/
│   │       └── User.php
│   ├── resources/views/admin/  Blade templates
│   │   ├── dashboard.blade.php
│   │   ├── products/
│   │   ├── categories/
│   │   ├── orders/
│   │   ├── users/
│   │   └── reports/
│   ├── routes/
│   │   ├── web.php             Admin panel routes
│   │   └── api.php             JWT-protected API routes
│   ├── tests/Feature/Admin/    Feature tests (21 tests)
│   ├── Dockerfile
│   └── .env.example
├── nginx/
│   └── nginx.conf              Reverse proxy configuration
├── docker-compose.yml          Full stack definition
└── README.md
```

---

## Postman Collection

Import both files from `api-gateway/docs/`:
- `ecommerce-api.postman_collection.json`
- `ecommerce-local.postman_environment.json`

Select the **Ecommerce Local** environment. Run **Auth → Login (Admin)** first — the JWT token saves automatically to all subsequent requests via a test script.

The collection covers:
- Auth (register, login with auto-token-save)
- Products (all endpoints including admin mutations)
- Orders (place order, my orders, admin management)
- Internal (cache invalidation, order sync — with and without API key to show 401)

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
