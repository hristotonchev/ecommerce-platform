import { Injectable, NotFoundException, Inject } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { CACHE_MANAGER } from '@nestjs/cache-manager';
import { Cache } from 'cache-manager';
import { Product } from '../entities/product.entity';
import { Inventory } from '../entities/inventory.entity';
import { CreateProductDto } from './dto/create-product.dto';
import { UpdateProductDto } from './dto/update-product.dto';
import { ProductQueryDto } from './dto/product-query.dto';

@Injectable()
export class ProductsService {
  constructor(
    @InjectRepository(Product)
    private productRepository: Repository<Product>,
    @InjectRepository(Inventory)
    private inventoryRepository: Repository<Inventory>,
    @Inject(CACHE_MANAGER)
    private cacheManager: Cache,
  ) {}

  async findAll(query: ProductQueryDto) {
    const { page = 1, limit = 10, search, category_id, is_active } = query;

    // Cache only simple queries without filters
    const cacheKey = !search && !category_id
      ? `products_list_p${page}_l${limit}`
      : null;

    if (cacheKey) {
      const cached = await this.cacheManager.get(cacheKey);
      if (cached) {
        console.log(`[Cache] HIT: ${cacheKey}`);
        return cached;
      }
    }

    const qb = this.productRepository
      .createQueryBuilder('product')
      .leftJoinAndSelect('product.category', 'category')
      .leftJoinAndSelect('product.inventory', 'inventory')
      .where('product.deleted_at IS NULL');

    if (search) {
      qb.andWhere(
        '(product.name ILIKE :search OR product.description ILIKE :search)',
        { search: `%${search}%` },
      );
    }
    if (category_id) {
      qb.andWhere('product.category_id = :category_id', { category_id });
    }
    if (is_active !== undefined) {
      qb.andWhere('product.is_active = :is_active', { is_active });
    }

    const total = await qb.getCount();
    const items = await qb
      .skip((page - 1) * limit)
      .take(limit)
      .getMany();

    const result = {
      data: items,
      meta: { total, page, limit, last_page: Math.ceil(total / limit) },
    };

    if (cacheKey) {
      await this.cacheManager.set(cacheKey, result, 300);
      console.log(`[Cache] SET: ${cacheKey}`);
    }

    return result;
  }

  async findOne(id: number): Promise<Product> {
    const cacheKey = `product_${id}`;
    const cached = await this.cacheManager.get<Product>(cacheKey);
    if (cached) {
      console.log(`[Cache] HIT: ${cacheKey}`);
      return cached;
    }

    const product = await this.productRepository.findOne({
      where: { id },
      relations: ['category', 'inventory'],
    });
    if (!product) throw new NotFoundException(`Product #${id} not found`);

    await this.cacheManager.set(cacheKey, product, 300);
    console.log(`[Cache] SET: ${cacheKey}`);
    return product;
  }

  async create(dto: CreateProductDto): Promise<Product> {
    const slug = dto.name.toLowerCase().replace(/\s+/g, '-') + '-' + Date.now();
    const product = this.productRepository.create({ ...dto, slug });
    const saved = await this.productRepository.save(product);

    await this.inventoryRepository.save(
      this.inventoryRepository.create({
        product_id: saved.id,
        quantity: dto.quantity,
        reserved: 0,
      }),
    );

    // Invalidate list cache
    await this.cacheManager.del('products_list');

    return this.findOne(saved.id);
  }

  async update(id: number, dto: UpdateProductDto): Promise<Product> {
    await this.findOne(id);

    if (dto.quantity !== undefined) {
      await this.inventoryRepository.update(
        { product_id: id },
        { quantity: dto.quantity },
      );
    }

    const { quantity, ...productData } = dto;
    await this.productRepository.update(id, productData);

    // Invalidate cache
    await this.cacheManager.del(`product_${id}`);
    await this.cacheManager.del('products_list');

    return this.findOne(id);
  }

  async remove(id: number): Promise<void> {
    await this.findOne(id);
    await this.productRepository.softDelete(id);

    // Invalidate cache
    await this.cacheManager.del(`product_${id}`);
    await this.cacheManager.del('products_list');
  }
}
