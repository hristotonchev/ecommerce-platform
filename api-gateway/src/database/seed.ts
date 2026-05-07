import { DataSource } from 'typeorm';
import * as bcrypt from 'bcryptjs';

const AppDataSource = new DataSource({
  type: 'postgres',
  host: process.env.DB_HOST || 'localhost',
  port: parseInt(process.env.DB_PORT || '5432'),
  username: process.env.DB_USER || 'ecommerce_user',
  password: process.env.DB_PASS || 'secret',
  database: process.env.DB_NAME || 'ecommerce',
  synchronize: false,
});

async function seed() {
  await AppDataSource.initialize();
  console.log('Seeding database...');

  const password = await bcrypt.hash('password123', 12);

  // PHP bcrypt uses $2y$ prefix, Node.js uses $2b$
  // They are identical algorithmically - just swap the prefix
  const phpPassword = password.replace('$2b$', '$2y$');

  await AppDataSource.query(`
    INSERT INTO users (name, email, password, role, created_at)
    VALUES
      ('Admin', 'admin@shop.com', '${phpPassword}', 'admin', NOW()),
      ('Maria Petrova', 'maria@example.com', '${password}', 'customer', NOW()),
      ('Georgi Ivanov', 'georgi@example.com', '${password}', 'customer', NOW())
    ON CONFLICT (email) DO NOTHING;
  `);
  console.log('Users done');

  // Categories
  await AppDataSource.query(`
    INSERT INTO categories (name, slug, created_at)
    VALUES
      ('Electronics', 'electronics', NOW()),
      ('Clothing', 'clothing', NOW()),
      ('Books', 'books', NOW())
    ON CONFLICT (slug) DO NOTHING;
  `);

  const [elec] = await AppDataSource.query(
    `SELECT id FROM categories WHERE slug = 'electronics'`
  );

  await AppDataSource.query(`
    INSERT INTO categories (name, slug, parent_id, created_at)
    VALUES
      ('Phones', 'phones', ${elec.id}, NOW()),
      ('Laptops', 'laptops', ${elec.id}, NOW())
    ON CONFLICT (slug) DO NOTHING;
  `);
  console.log('Categories done');

  // Products
  const [phones]  = await AppDataSource.query(`SELECT id FROM categories WHERE slug='phones'`);
  const [laptops] = await AppDataSource.query(`SELECT id FROM categories WHERE slug='laptops'`);
  const [books]   = await AppDataSource.query(`SELECT id FROM categories WHERE slug='books'`);

  const products = [
    { name: 'iPhone 15',   price: 999.99,  cat: phones.id,  qty: 50,  desc: 'Latest Apple smartphone' },
    { name: 'Samsung S24', price: 849.99,  cat: phones.id,  qty: 30,  desc: 'Flagship Android phone' },
    { name: 'MacBook Pro', price: 2499.99, cat: laptops.id, qty: 15,  desc: 'Professional laptop' },
    { name: 'Dell XPS 15', price: 1799.99, cat: laptops.id, qty: 10,  desc: 'Premium Windows laptop' },
    { name: 'Clean Code',  price: 34.99,   cat: books.id,   qty: 100, desc: 'Robert C. Martin' },
  ];

  const productIds: number[] = [];

  for (const p of products) {
    const slug = p.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-' + Date.now();
    const result = await AppDataSource.query(`
      INSERT INTO products (name, slug, description, price, category_id, is_active, created_at)
      VALUES ('${p.name}', '${slug}', '${p.desc}', ${p.price}, ${p.cat}, true, NOW())
      RETURNING id;
    `);

    const productId = result[0].id;
    productIds.push(productId);

    await AppDataSource.query(`
      INSERT INTO inventory (product_id, quantity, reserved, updated_at)
      VALUES (${productId}, ${p.qty}, 0, NOW())
      ON CONFLICT (product_id) DO NOTHING;
    `);
  }
  console.log('Products done');

  // Orders
  const [customer] = await AppDataSource.query(
    `SELECT id FROM users WHERE email = 'maria@example.com'`
  );

  const [order1] = await AppDataSource.query(`
    INSERT INTO orders (user_id, status, total_amount, notes, created_at, updated_at)
    VALUES (${customer.id}, 'confirmed', 999.99, 'Please deliver in the morning', NOW(), NOW())
    RETURNING id;
  `);

  await AppDataSource.query(`
    INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
    VALUES (${order1.id}, ${productIds[0]}, 1, 999.99, 999.99);
  `);

  const [order2] = await AppDataSource.query(`
    INSERT INTO orders (user_id, status, total_amount, notes, created_at, updated_at)
    VALUES (${customer.id}, 'shipped', 2534.98, NULL, NOW(), NOW())
    RETURNING id;
  `);

  await AppDataSource.query(`
    INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
    VALUES
      (${order2.id}, ${productIds[2]}, 1, 2499.99, 2499.99),
      (${order2.id}, ${productIds[4]}, 1, 34.99, 34.99);
  `);

  console.log('Orders done');
  await AppDataSource.destroy();
  console.log('Seed complete!');
}

seed().catch(console.error);
