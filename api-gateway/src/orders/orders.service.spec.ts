import { Test, TestingModule } from '@nestjs/testing';
import { getRepositoryToken } from '@nestjs/typeorm';
import { DataSource } from 'typeorm';
import { NotFoundException } from '@nestjs/common';
import { OrdersService } from './orders.service';
import { Order, OrderStatus } from '../entities/order.entity';
import { OrderItem } from '../entities/order-item.entity';
import { Product } from '../entities/product.entity';
import { Inventory } from '../entities/inventory.entity';
import { OrdersGateway } from '../websockets/orders.gateway';

const mockOrder = {
  id: 1, user_id: 1, status: OrderStatus.CONFIRMED,
  total_amount: 999.99, notes: null,
  items: [{ id: 1, product_id: 1, quantity: 1, unit_price: 999.99, subtotal: 999.99 }],
  user: { id: 1, name: 'Test', email: 'test@test.com' },
  created_at: new Date(),
};

const mockOrderRepository = {
  findOne:      jest.fn(),
  findAndCount: jest.fn(),
  find:         jest.fn(),
  update:       jest.fn(),
  create:       jest.fn(),
  save:         jest.fn(),
};

const mockOrderItemRepository = { create: jest.fn(), save: jest.fn() };
const mockProductRepository   = { findOne: jest.fn() };
const mockInventoryRepository = { findOne: jest.fn(), update: jest.fn() };
const mockDataSource          = { transaction: jest.fn() };
const mockOrdersGateway       = {
  notifyOrderUpdate:     jest.fn(),
  broadcastOrderCreated: jest.fn(),
};

describe('OrdersService', () => {
  let service: OrdersService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        OrdersService,
        { provide: getRepositoryToken(Order),     useValue: mockOrderRepository },
        { provide: getRepositoryToken(OrderItem), useValue: mockOrderItemRepository },
        { provide: getRepositoryToken(Product),   useValue: mockProductRepository },
        { provide: getRepositoryToken(Inventory), useValue: mockInventoryRepository },
        { provide: DataSource,                    useValue: mockDataSource },
        { provide: OrdersGateway,                 useValue: mockOrdersGateway },
      ],
    }).compile();

    service = module.get<OrdersService>(OrdersService);
    jest.clearAllMocks();
  });

  describe('findOne', () => {
    it('should return an order by id', async () => {
      mockOrderRepository.findOne.mockResolvedValue(mockOrder);
      const result = await service.findOne(1);
      expect(result.id).toBe(1);
      expect(result.status).toBe(OrderStatus.CONFIRMED);
    });

    it('should throw NotFoundException when order not found', async () => {
      mockOrderRepository.findOne.mockResolvedValue(null);
      await expect(service.findOne(999)).rejects.toThrow(NotFoundException);
    });

    it('should filter by userId when provided', async () => {
      mockOrderRepository.findOne.mockResolvedValue(mockOrder);
      await service.findOne(1, 1);
      expect(mockOrderRepository.findOne).toHaveBeenCalledWith(
        expect.objectContaining({ where: { id: 1, user_id: 1 } })
      );
    });
  });

  describe('findAll', () => {
    it('should return paginated orders', async () => {
      mockOrderRepository.findAndCount.mockResolvedValue([[mockOrder], 1]);
      const result = await service.findAll(1, 10);
      expect(result.data).toHaveLength(1);
      expect(result.meta.total).toBe(1);
      expect(result.meta.page).toBe(1);
      expect(result.meta.last_page).toBe(1);
    });
  });

  describe('updateStatus', () => {
    it('should update order status', async () => {
      mockOrderRepository.findOne.mockResolvedValue(mockOrder);
      mockOrderRepository.update.mockResolvedValue({ affected: 1 });

      await service.updateStatus(1, OrderStatus.SHIPPED);

      expect(mockOrderRepository.update).toHaveBeenCalledWith(
        1, { status: OrderStatus.SHIPPED }
      );
      expect(mockOrdersGateway.notifyOrderUpdate).toHaveBeenCalled();
    });

    it('should release inventory when order is cancelled', async () => {
      mockOrderRepository.findOne.mockResolvedValue(mockOrder);
      mockOrderRepository.update.mockResolvedValue({ affected: 1 });
      mockInventoryRepository.update.mockResolvedValue({ affected: 1 });

      await service.updateStatus(1, OrderStatus.CANCELLED);

      expect(mockInventoryRepository.update).toHaveBeenCalled();
    });
  });
});
