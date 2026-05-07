import { Test, TestingModule } from '@nestjs/testing';
import { OrdersService } from './orders.service';
import { getRepositoryToken } from '@nestjs/typeorm';
import { Order, OrderStatus } from '../entities/order.entity';
import { OrderItem } from '../entities/order-item.entity';
import { Product } from '../entities/product.entity';
import { Inventory } from '../entities/inventory.entity';
import { DataSource } from 'typeorm';
import { NotFoundException, BadRequestException } from '@nestjs/common';

const mockOrder = {
  id: 1,
  user_id: 1,
  status: OrderStatus.CONFIRMED,
  total_amount: 999.99,
  notes: 'Test order',
  items: [
    { id: 1, product_id: 1, quantity: 1, unit_price: 999.99, subtotal: 999.99 }
  ],
};

const mockProduct = {
  id: 1,
  name: 'iPhone 15',
  price: 999.99,
  is_active: true,
};

const mockInventory = {
  id: 1,
  product_id: 1,
  quantity: 50,
  reserved: 0,
};

const mockOrderRepository = {
  findOne: jest.fn(),
  find: jest.fn(),
  findAndCount: jest.fn(),
  update: jest.fn(),
  create: jest.fn(),
  save: jest.fn(),
};

const mockInventoryRepository = {
  update: jest.fn(),
};

const mockDataSource = {
  transaction: jest.fn(async (cb) => {
    const manager = {
      findOne: jest.fn((entity) => {
        if (entity === Product) return Promise.resolve(mockProduct);
        if (entity === Inventory) return Promise.resolve(mockInventory);
        return Promise.resolve(null);
      }),
      update: jest.fn().mockResolvedValue({}),
      create: jest.fn((entity, data) => data),
      save: jest.fn().mockResolvedValue({ id: 1, ...mockOrder }),
    };
    return cb(manager);
  }),
};

describe('OrdersService', () => {
  let service: OrdersService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        OrdersService,
        { provide: getRepositoryToken(Order), useValue: mockOrderRepository },
        { provide: getRepositoryToken(OrderItem), useValue: {} },
        { provide: getRepositoryToken(Product), useValue: {} },
        { provide: getRepositoryToken(Inventory), useValue: mockInventoryRepository },
        { provide: DataSource, useValue: mockDataSource },
      ],
    }).compile();

    service = module.get<OrdersService>(OrdersService);
    jest.clearAllMocks();
  });

  describe('findOne', () => {
    it('should return an order by id', async () => {
      mockOrderRepository.findOne.mockResolvedValue(mockOrder);
      const result = await service.findOne(1);
      expect(result).toEqual(mockOrder);
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
    });
  });

  describe('updateStatus', () => {
    it('should update order status', async () => {
      mockOrderRepository.findOne.mockResolvedValue(mockOrder);
      mockOrderRepository.update.mockResolvedValue({ affected: 1 });

      await service.updateStatus(1, OrderStatus.SHIPPED);
      expect(mockOrderRepository.update).toHaveBeenCalledWith(1, { status: OrderStatus.SHIPPED });
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
