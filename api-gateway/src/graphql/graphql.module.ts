import { Module } from '@nestjs/common';
import { ProductsResolver } from './resolvers/products.resolver';
import { OrdersResolver } from './resolvers/orders.resolver';
import { ProductsModule } from '../products/products.module';
import { OrdersModule } from '../orders/orders.module';

@Module({
  imports: [ProductsModule, OrdersModule],
  providers: [ProductsResolver, OrdersResolver],
})
export class GraphqlModule {}
