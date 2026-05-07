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
  console.log('🌱 Starting seed...');

  // Users
  const hashedPassword = await bcrypt.hash('password123', 12);

  await AppDataSource.query(`
    INSERT INTO users (name, email, password, role, created_at)
    VALUES
      ('Admin User', 'admin@ecommerce.com', '${hashedPassword}', 'admin', NOW()),
      ('John Customer', 'john@example.com', '${hashedPassword}', 'customer', NOW()),
      ('Jane Customer', 'jane@example.com', '${hashedPassword}', 'customer', NOW())
    ON CONFLICT (email) DO NOTHING;
  `);
  console.log('✅ Users seeded');

  // Categories
  await AppDataSource.query(`
    INSERT INTO categories (name, slug, created_at)
    VALUES
      ('Electronics', 'electronics', NOW()),
      ('Clothing', 'clothing', NOW()),
      ('Books', 'books', NOW()),
      ('Sports', 'sports', NOW())
    ON CONFLICT (slug) DO NOTHING;
  `);

  const electronics = await AppDataSource.query(
    `SELECT id FROM categories WHERE slug = 'electronics'`
  );

  await AppDataSource.query(`
    INSERT INTO categories (name, slug, parent_id, created_at)
    VALUES
      ('Phones', 'phones', ${electronics[0].id}, NOW()),
      ('Laptops', 'laptops', ${electronics[0].id}, NOW())
    ON CONFLICT (slug) DO NOTHING;
  `);
  console.log('✅ Categories seeded');

  // Products
  const phones = await AppDataSource.query(
    `SELECT id FROM categories WHERE slug = 'phones'`
  );
  const laptops = await AppDataSource.query(
    `SELECT id FROM categories WHERE slug = 'laptops'`
  );

  const products = [
    { name: 'iPhone 15 Pro', price: 999.99, category_id: phones[0].id, qty: 50 },
    { name: 'Samsung Galaxy S24', price: 849.99, category_id: phones[0].id, qty: 30 },
    { name: 'MacBook Pro 16"', price: 2499.99, category_id: laptops[0].id, qty: 20 },
    { name: 'Dell XPS 15', price: 1799.99, category_id: laptops[0].id, qty: 15 },
    { name: 'AirPods Pro', price: 249.99, category_id: electronics[0].id, qty: 100 },
  ];

  for (const p of products) {
    const result = await AppDataSource.query(`
      INSERT INTO products (name, slug, description, price, category_id, is_active, created_at)
      VALUES (
        '${p.name}',
        '${p.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-${Date.now()}',
        'High quality ${p.name}',
        ${p.price},
        ${p.category_id},
        true,
        NOW()
      )
      ON CONFLICT (slug) DO NOTHING
      RETURNING id;
    `);

    if (result.length > 0) {
      await AppDataSource.query(`
        INSERT INTO inventory (product_id, quantity, reserved, updated_at)
        VALUES (${result[0].id}, ${p.qty}, 0, NOW())
        ON CONFLICT (product_id) DO NOTHING;
      `);
    }
  }
  console.log('✅ Products seeded');

  await AppDataSource.destroy();
  console.log('🎉 Seed complete!');
}

seed().catch(console.error);
