import { Module, NestModule, MiddlewareConsumer } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CacheModule } from '@nestjs/cache-manager';
import { GraphQLModule } from '@nestjs/graphql';
import { ApolloDriver, ApolloDriverConfig } from '@nestjs/apollo';
import { join } from 'path';
import { AuthModule } from './auth/auth.module';
import { ProductsModule } from './products/products.module';
import { OrdersModule } from './orders/orders.module';
import { InternalModule } from './internal/internal.module';
import { WebsocketsModule } from './websockets/websockets.module';
import { GraphqlModule } from './graphql/graphql.module';
import { SearchModule } from './search/search.module';
import { LoggingMiddleware } from './common/middleware/logging.middleware';
import { User } from './entities/user.entity';
import { Category } from './entities/category.entity';
import { Product } from './entities/product.entity';
import { Inventory } from './entities/inventory.entity';
import { Order } from './entities/order.entity';
import { OrderItem } from './entities/order-item.entity';

@Module({
  imports: [
    ConfigModule.forRoot({ isGlobal: true }),
    // TODO: Switch to the Redis store for production.
    //       With the current in-memory store the cache is not shared between
    //       multiple Nest.js instances (horizontal scaling breaks caching) and
    //       is lost on every restart.  Use `cache-manager-ioredis-yet` or
    //       `@nestjs/cache-manager` with `redisStore` from `cache-manager-redis-store`.
    CacheModule.registerAsync({
      isGlobal: true,
      imports: [ConfigModule],
      useFactory: () => ({ store: 'memory', ttl: 300, max: 100 }),
      inject: [ConfigService],
    }),
    GraphQLModule.forRoot<ApolloDriverConfig>({
      driver: ApolloDriver,
      autoSchemaFile: join(process.cwd(), 'src/schema.gql'),
      sortSchema: true,
      playground: true,
      context: ({ req }) => ({ req }),
    }),
    TypeOrmModule.forRootAsync({
      imports: [ConfigModule],
      useFactory: (config: ConfigService) => ({
        type: 'postgres',
        host: config.get('DB_HOST', 'localhost'),
        port: config.get<number>('DB_PORT', 5432),
        username: config.get('DB_USER', 'ecommerce_user'),
        password: config.get('DB_PASS', 'secret'),
        database: config.get('DB_NAME', 'ecommerce'),
        entities: [User, Category, Product, Inventory, Order, OrderItem],
        // TODO: Disable `synchronize` in production and rely on explicit migration
        //       files instead.  Auto-sync can cause accidental column drops or
        //       type changes against a live database.
        synchronize: true,
        logging: false,
      }),
      inject: [ConfigService],
    }),
    AuthModule,
    ProductsModule,
    OrdersModule,
    InternalModule,
    WebsocketsModule,
    GraphqlModule,
    SearchModule,
  ],
})
export class AppModule implements NestModule {
  configure(consumer: MiddlewareConsumer) {
    consumer.apply(LoggingMiddleware).forRoutes('*');
  }
}
