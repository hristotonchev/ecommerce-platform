import { Test, TestingModule } from '@nestjs/testing';
import { ProductsService } from './products.service';
import { getRepositoryToken } from '@nestjs/typeorm';
import { Product } from '../entities/product.entity';
import { Inventory } from '../entities/inventory.entity';
import { NotFoundException } from '@nestjs/common';

const mockProductRepository = {
  createQueryBuilder: jest.fn(() => ({
    leftJoinAndSelect: jest.fn().mockReturnThis(),
    where: jest.fn().mockReturnThis(),
    andWhere: jest.fn().mockReturnThis(),
    getCount: jest.fn().mockResolvedValue(1),
    skip: jest.fn().mockReturnThis(),
    take: jest.fn().mockReturnThis(),
    getMany: jest.fn().mockResolvedValue([mockProduct]),
  })),
  findOne: jest.fn(),
  create: jest.fn(),
  save: jest.fn(),
  update: jest.fn(),
  softDelete: jest.fn(),
};

const mockInventoryRepository = {
  create: jest.fn(),
  save: jest.fn(),
  update: jest.fn(),
};

const mockProduct = {
  id: 1,
  name: 'iPhone 15',
  slug: 'iphone-15',
  description: 'Latest iPhone',
  price: 999.99,
  category_id: 1,
  is_active: true,
  image_path: null,
  created_at: new Date(),
  deleted_at: null,
  category: { id: 1, name: 'Electronics' },
  inventory: { id: 1, product_id: 1, quantity: 50, reserved: 0 },
};

describe('ProductsService', () => {
  let service: ProductsService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        ProductsService,
        { provide: getRepositoryToken(Product), useValue: mockProductRepository },
        { provide: getRepositoryToken(Inventory), useValue: mockInventoryRepository },
      ],
    }).compile();

    service = module.get<ProductsService>(ProductsService);
    jest.clearAllMocks();
  });

  describe('findAll', () => {
    it('should return paginated products', async () => {
      const result = await service.findAll({ page: 1, limit: 10 });

      expect(result.data).toHaveLength(1);
      expect(result.meta.total).toBe(1);
      expect(result.meta.page).toBe(1);
    });

    it('should apply search filter', async () => {
      const qbMock = {
        leftJoinAndSelect: jest.fn().mockReturnThis(),
        where: jest.fn().mockReturnThis(),
        andWhere: jest.fn().mockReturnThis(),
        getCount: jest.fn().mockResolvedValue(1),
        skip: jest.fn().mockReturnThis(),
        take: jest.fn().mockReturnThis(),
        getMany: jest.fn().mockResolvedValue([mockProduct]),
      };
      mockProductRepository.createQueryBuilder.mockReturnValue(qbMock);

      await service.findAll({ page: 1, limit: 10, search: 'iPhone' });
      expect(qbMock.andWhere).toHaveBeenCalled();
    });
  });

  describe('findOne', () => {
    it('should return a product by id', async () => {
      mockProductRepository.findOne.mockResolvedValue(mockProduct);
      const result = await service.findOne(1);
      expect(result).toEqual(mockProduct);
    });

    it('should throw NotFoundException when product not found', async () => {
      mockProductRepository.findOne.mockResolvedValue(null);
      await expect(service.findOne(999)).rejects.toThrow(NotFoundException);
    });
  });

  describe('create', () => {
    it('should create a product with inventory', async () => {
      const dto = {
        name: 'MacBook Pro',
        description: 'Laptop',
        price: 1999.99,
        category_id: 1,
        quantity: 10,
      };

      mockProductRepository.create.mockReturnValue({ ...dto, slug: 'macbook-pro' });
      mockProductRepository.save.mockResolvedValue({ id: 2, ...dto });
      mockProductRepository.findOne.mockResolvedValue({ id: 2, ...dto, inventory: { quantity: 10 } });
      mockInventoryRepository.create.mockReturnValue({ product_id: 2, quantity: 10 });
      mockInventoryRepository.save.mockResolvedValue({ id: 1, product_id: 2, quantity: 10 });

      const result = await service.create(dto);
      expect(mockProductRepository.save).toHaveBeenCalled();
      expect(mockInventoryRepository.save).toHaveBeenCalled();
      expect(result.id).toBe(2);
    });
  });

  describe('remove', () => {
    it('should soft delete a product', async () => {
      mockProductRepository.findOne.mockResolvedValue(mockProduct);
      mockProductRepository.softDelete.mockResolvedValue({ affected: 1 });

      await service.remove(1);
      expect(mockProductRepository.softDelete).toHaveBeenCalledWith(1);
    });

    it('should throw NotFoundException when deleting non-existent product', async () => {
      mockProductRepository.findOne.mockResolvedValue(null);
      await expect(service.remove(999)).rejects.toThrow(NotFoundException);
    });
  });
});
