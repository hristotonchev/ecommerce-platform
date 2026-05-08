import {
  Injectable, NotFoundException,
  BadRequestException, Inject, forwardRef,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, DataSource } from 'typeorm';
import { Order, OrderStatus } from '../entities/order.entity';
import { OrderItem } from '../entities/order-item.entity';
import { Product } from '../entities/product.entity';
import { Inventory } from '../entities/inventory.entity';
import { CreateOrderDto } from './dto/create-order.dto';
import { OrdersGateway } from '../websockets/orders.gateway';
import { OrderConfirmationJob } from '../jobs/order-confirmation.job';

@Injectable()
export class OrdersService {
  constructor(
    @InjectRepository(Order)
    private orderRepository: Repository<Order>,
    @InjectRepository(OrderItem)
    private orderItemRepository: Repository<OrderItem>,
    @InjectRepository(Product)
    private productRepository: Repository<Product>,
    @InjectRepository(Inventory)
    private inventoryRepository: Repository<Inventory>,
    private dataSource: DataSource,
    @Inject(forwardRef(() => OrdersGateway))
    private ordersGateway: OrdersGateway,
  ) {}

  async create(userId: number, dto: CreateOrderDto): Promise<Order> {
    let savedOrderId = 0;
    const orderItemsForEmail: any[] = [];

    await this.dataSource.transaction(async (manager) => {
      let total = 0;
      const orderItems: Partial<OrderItem>[] = [];

      for (const item of dto.items) {
        const product = await manager.findOne(Product, {
          where: { id: item.product_id, is_active: true },
        });
        if (!product) {
          throw new NotFoundException(`Product #${item.product_id} not found`);
        }

        const inventory = await manager.findOne(Inventory, {
          where: { product_id: item.product_id },
        });

        const available = (inventory?.quantity ?? 0) - (inventory?.reserved ?? 0);
        if (available < item.quantity) {
          throw new BadRequestException(
            `Insufficient stock for "${product.name}". Available: ${available}`
          );
        }

        await manager.update(Inventory, { product_id: item.product_id }, {
          reserved: () => `reserved + ${item.quantity}`,
        });

        const subtotal = Number(product.price) * item.quantity;
        total += subtotal;

        orderItems.push({
          product_id: item.product_id,
          quantity:   item.quantity,
          unit_price: Number(product.price),
          subtotal,
        });

        orderItemsForEmail.push({
          productName: product.name,
          quantity:    item.quantity,
          unitPrice:   Number(product.price),
          subtotal,
        });
      }

      const order = manager.create(Order, {
        user_id:      userId,
        total_amount: total,
        notes:        dto.notes,
        status:       OrderStatus.PENDING,
      });
      const savedOrder = await manager.save(Order, order);
      savedOrderId = savedOrder.id;

      for (const item of orderItems) {
        await manager.save(OrderItem, manager.create(OrderItem, {
          ...item,
          order_id: savedOrderId,
        }));
      }
    });

    // Mock payment
    console.log(`[Payment] Processing payment for order #${savedOrderId} - SUCCESS`);
    await this.orderRepository.update(savedOrderId, { status: OrderStatus.CONFIRMED });

    const order = await this.findOne(savedOrderId);

    // Queue email job (async — non-blocking)
    const user = order.user as any;
    OrderConfirmationJob.process({
      orderId:     order.id,
      userEmail:   user?.email ?? 'customer@example.com',
      userName:    user?.name  ?? 'Customer',
      totalAmount: Number(order.total_amount),
      items:       orderItemsForEmail,
    }).catch(err => console.error('[EmailQueue] Failed:', err.message));

    // WebSocket notification
    this.ordersGateway.notifyOrderUpdate(userId, order);
    this.ordersGateway.broadcastOrderCreated(order);

    return order;
  }

  async findOne(id: number, userId?: number): Promise<Order> {
    const where: any = { id };
    if (userId) where.user_id = userId;

    const order = await this.orderRepository.findOne({
      where,
      relations: ['items', 'items.product', 'user'],
    });

    if (!order) throw new NotFoundException(`Order #${id} not found`);
    return order;
  }

  async findUserOrders(userId: number) {
    return this.orderRepository.find({
      where:     { user_id: userId },
      relations: ['items', 'items.product'],
      order:     { created_at: 'DESC' },
    });
  }

  async findAll(page = 1, limit = 10) {
    const [data, total] = await this.orderRepository.findAndCount({
      relations: ['items', 'user'],
      order:     { created_at: 'DESC' },
      skip:      (page - 1) * limit,
      take:      limit,
    });

    return {
      data,
      meta: { total, page, limit, last_page: Math.ceil(total / limit) },
    };
  }

  async updateStatus(id: number, status: OrderStatus): Promise<Order> {
    const order = await this.findOne(id);

    if (status === OrderStatus.CANCELLED) {
      for (const item of order.items) {
        await this.inventoryRepository.update(
          { product_id: item.product_id },
          { reserved: () => `GREATEST(reserved - ${item.quantity}, 0)` },
        );
      }
    }

    if (status === OrderStatus.SHIPPED) {
      for (const item of order.items) {
        await this.inventoryRepository.update(
          { product_id: item.product_id },
          {
            quantity: () => `GREATEST(quantity - ${item.quantity}, 0)`,
            reserved: () => `GREATEST(reserved - ${item.quantity}, 0)`,
          },
        );
      }
    }

    await this.orderRepository.update(id, { status });
    const updated = await this.findOne(id);

    this.ordersGateway.notifyOrderUpdate(updated.user_id, updated);

    return updated;
  }
}
