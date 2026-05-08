import { Test, TestingModule } from '@nestjs/testing';
import { getRepositoryToken } from '@nestjs/typeorm';
import { CACHE_MANAGER } from '@nestjs/cache-manager';
import { NotFoundException } from '@nestjs/common';
import { ProductsService } from './products.service';
import { Product } from '../entities/product.entity';
import { Inventory } from '../entities/inventory.entity';

const mockProduct = {
  id: 1, name: 'iPhone 15', slug: 'iphone-15',
  description: 'Latest Apple smartphone', price: 999.99,
  category_id: 1, is_active: true, deleted_at: null,
  category:  { id: 1, name: 'Phones' },
  inventory: { quantity: 50, reserved: 0 },
};

// QueryBuilder mock chain
const mockQb = {
  leftJoinAndSelect: jest.fn().mockReturnThis(),
  where:             jest.fn().mockReturnThis(),
  andWhere:          jest.fn().mockReturnThis(),
  orderBy:           jest.fn().mockReturnThis(),
  skip:              jest.fn().mockReturnThis(),
  take:              jest.fn().mockReturnThis(),
  getCount:          jest.fn().mockResolvedValue(1),
  getMany:           jest.fn().mockResolvedValue([mockProduct]),
  getManyAndCount:   jest.fn().mockResolvedValue([[mockProduct], 1]),
};

const mockProductRepository = {
  createQueryBuilder: jest.fn().mockReturnValue(mockQb),
  findOne:            jest.fn(),
  create:             jest.fn(),
  save:               jest.fn(),
  update:             jest.fn(),
  softDelete:         jest.fn(),
};

const mockInventoryRepository = {
  create:  jest.fn(),
  save:    jest.fn(),
  findOne: jest.fn(),
};

const mockCacheManager = {
  get: jest.fn().mockResolvedValue(null),
  set: jest.fn().mockResolvedValue(undefined),
  del: jest.fn().mockResolvedValue(undefined),
};

describe('ProductsService', () => {
  let service: ProductsService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        ProductsService,
        { provide: getRepositoryToken(Product),   useValue: mockProductRepository },
        { provide: getRepositoryToken(Inventory), useValue: mockInventoryRepository },
        { provide: CACHE_MANAGER,                 useValue: mockCacheManager },
      ],
    }).compile();

    service = module.get<ProductsService>(ProductsService);
    mockQb.getCount.mockResolvedValue(1);
    mockQb.getMany.mockResolvedValue([mockProduct]);
    jest.clearAllMocks();

    mockCacheManager.get.mockResolvedValue(null);
    mockCacheManager.set.mockResolvedValue(undefined);
    mockCacheManager.del.mockResolvedValue(undefined);
    mockProductRepository.createQueryBuilder.mockReturnValue(mockQb);
    mockQb.leftJoinAndSelect.mockReturnThis();
    mockQb.where.mockReturnThis();
    mockQb.andWhere.mockReturnThis();
    mockQb.orderBy.mockReturnThis();
    mockQb.skip.mockReturnThis();
    mockQb.take.mockReturnThis();
    mockQb.getManyAndCount.mockResolvedValue([[mockProduct], 1]);
  });

  describe('findAll', () => {
    it('should return paginated products', async () => {
      const result = await service.findAll({ page: 1, limit: 10 });
      // @ts-ignore
      expect(result.data).toHaveLength(1);
      // @ts-ignore
      expect(result.meta.total).toBe(1);
      // @ts-ignore
      expect(result.meta.page).toBe(1);
    });

    it('should apply search filter', async () => {
      const result = await service.findAll({ page: 1, limit: 10, search: 'iPhone' });
      // @ts-ignore
      expect(result.data).toHaveLength(1);
      expect(mockQb.andWhere).toHaveBeenCalled();
    });
  });

  describe('findOne', () => {
    it('should return a product by id', async () => {
      mockProductRepository.findOne.mockResolvedValue(mockProduct);
      const result = await service.findOne(1);
      expect(result.id).toBe(1);
      expect(result.name).toBe('iPhone 15');
    });

    it('should throw NotFoundException when product not found', async () => {
      mockProductRepository.findOne.mockResolvedValue(null);
      await expect(service.findOne(999)).rejects.toThrow(NotFoundException);
    });
  });

  describe('create', () => {
    it('should create a product with inventory', async () => {
      const dto = {
        name: 'Test Product', description: 'Test description',
        price: 99.99, category_id: 1, quantity: 10,
      };
      const saved = { ...mockProduct, ...dto, id: 2 };

      mockProductRepository.create.mockReturnValue(saved);
      mockProductRepository.save.mockResolvedValue(saved);
      mockProductRepository.findOne.mockResolvedValue(saved);
      mockInventoryRepository.create.mockReturnValue({ product_id: 2, quantity: 10 });
      mockInventoryRepository.save.mockResolvedValue({});

      const result = await service.create(dto);
      expect(result.name).toBe('Test Product');
      expect(mockInventoryRepository.save).toHaveBeenCalled();
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
