import { Injectable, Inject } from '@nestjs/common';
import { CACHE_MANAGER } from '@nestjs/cache-manager';
import type { Cache } from 'cache-manager';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Order } from '../entities/order.entity';

@Injectable()
export class InternalService {
  constructor(
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    @InjectRepository(Order)
    private orderRepository: Repository<Order>,
  ) {}

  async invalidateCache(type: string, id: number) {
    // Invalidate specific product cache
    await this.cacheManager.del(`product_${id}`);
    // Invalidate products list cache
    await this.cacheManager.del('products_list');

    console.log(`[Cache] Invalidated cache for ${type} #${id}`);
    return { success: true, message: `Cache invalidated for ${type} #${id}` };
  }

  async updateOrderStatus(id: number, status: string) {
    await this.orderRepository.update(id, { status: status as any });
    console.log(`[Internal] Order #${id} status updated to ${status}`);
    return { success: true, orderId: id, status };
  }
}
