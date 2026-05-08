import { Injectable, Inject, forwardRef } from '@nestjs/common';
import { CACHE_MANAGER } from '@nestjs/cache-manager';
import type { Cache } from 'cache-manager';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Order } from '../entities/order.entity';
import { OrdersGateway } from '../websockets/orders.gateway';

@Injectable()
export class InternalService {
  constructor(
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    @InjectRepository(Order)
    private orderRepository: Repository<Order>,
    // forwardRef avoids the circular dependency between InternalModule ↔ WebsocketsModule
    @Inject(forwardRef(() => OrdersGateway))
    private ordersGateway: OrdersGateway,
  ) {}

  async invalidateCache(type: string, id: number) {
    // Invalidate individual product cache entry.
    // NOTE: The key format must match what ProductsService uses (`product:${id}`).
    await this.cacheManager.del(`product:${id}`);

    // Invalidate common paginated list keys (pages 1-5, limits 10 & 20).
    // TODO: Once the cache store is Redis, use SCAN + DEL `products:*` instead
    //       so every cached page is guaranteed to be cleared, not just the first few.
    const limits  = [10, 20];
    const pages   = [1, 2, 3, 4, 5];
    const actives = ['', '_a0', '_a1'];
    const deletes: Promise<any>[] = [];
    for (const limit of limits) {
      for (const page of pages) {
        for (const active of actives) {
          deletes.push(this.cacheManager.del(`products:p${page}:l${limit}${active}`));
        }
      }
    }
    await Promise.all(deletes);

    console.log(`[Cache] Invalidated cache for ${type} #${id}`);
    return { success: true, message: `Cache invalidated for ${type} #${id}` };
  }

  async updateOrderStatus(id: number, status: string) {
    await this.orderRepository.update(id, { status: status as any });

    // Retrieve the updated order so we can push the real data to the customer.
    // Without this the WebSocket push was silently skipped, breaking the
    // Laravel → Nest.js → Customer real-time flow described in the architecture.
    const order = await this.orderRepository.findOne({
      where: { id },
      relations: ['user'],
    });

    if (order) {
      this.ordersGateway.notifyOrderUpdate(order.user_id, order);
    }

    console.log(`[Internal] Order #${id} status synced to ${status}`);
    return { success: true, orderId: id, status };
  }
}
