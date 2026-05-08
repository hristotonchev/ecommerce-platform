import { Injectable, NotFoundException, Inject } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { CACHE_MANAGER } from '@nestjs/cache-manager';
import type { Cache } from 'cache-manager';
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

  /**
   * Build a deterministic cache key from all active query params so that
   * different filter combinations never share the same cached result.
   */
  private buildListCacheKey(query: ProductQueryDto): string | null {
    const { page = 1, limit = 10, search, category_id, is_active } = query;

    // Only cache unfiltered listing pages to avoid an unbounded number of keys.
    // TODO: Switch to Redis key-tagging (e.g. ioredis + cache tags) so we can
    //       cache filtered results and still invalidate them atomically when a
    //       product changes — not easily doable with the current in-memory store.
    if (search || category_id) return null;

    // Include is_active in the key so admin (is_active=false) and storefront
    // (is_active=true / undefined) queries don't share the same cached page.
    const activeSegment = is_active !== undefined ? `_a${Number(is_active)}` : '';
    return `products:p${page}:l${limit}${activeSegment}`;
  }

  async findAll(query: ProductQueryDto) {
    const { page = 1, limit = 10, search, category_id, is_active } = query;

    const cacheKey = this.buildListCacheKey(query);

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
    const cacheKey = `product:${id}`;
    const cached = await this.cacheManager.get<Product>(cacheKey);
    if (cached) {
      console.log(`[Cache] HIT: ${cacheKey}`);
      return cached;
    }

    // Explicitly filter soft-deleted rows; TypeORM's @DeleteDateColumn adds a
    // global scope for repository methods, but using findOne without withDeleted
    // can still return deleted records when the entity is fetched inside a
    // transaction manager — being explicit here prevents that edge case.
    const product = await this.productRepository.findOne({
      where: { id, deleted_at: null as any },
      relations: ['category', 'inventory'],
    });
    if (!product) throw new NotFoundException(`Product #${id} not found`);

    await this.cacheManager.set(cacheKey, product, 300);
    console.log(`[Cache] SET: ${cacheKey}`);
    return product;
  }

  async create(dto: CreateProductDto): Promise<Product> {
    // TODO: Use a proper slugify library (e.g. `slugify`) to handle unicode,
    //       special chars, and duplicates (DB unique constraint is the safety net
    //       right now, but the UX on collision is poor).
    const slug = dto.name
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .trim() + '-' + Date.now();

    const product = this.productRepository.create({ ...dto, slug });
    const saved = await this.productRepository.save(product);

    await this.inventoryRepository.save(
      this.inventoryRepository.create({
        product_id: saved.id,
        quantity: dto.quantity,
        reserved: 0,
      }),
    );

    await this.invalidateProductListCache();

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

    await this.cacheManager.del(`product:${id}`);
    await this.invalidateProductListCache();

    return this.findOne(id);
  }

  async remove(id: number): Promise<void> {
    await this.findOne(id);
    await this.productRepository.softDelete(id);

    await this.cacheManager.del(`product:${id}`);
    await this.invalidateProductListCache();
  }

  /**
   * Clears all paginated product-list cache entries.
   *
   * The in-memory store doesn't support key-pattern deletion, so we delete the
   * most commonly cached pages (1–5 for limits 10 and 20).  This is a pragmatic
   * trade-off for the current scale.
   *
   * TODO: Replace with a Redis SCAN + DEL pattern (`SCAN 0 MATCH products:*`)
   *       once the cache store is migrated to Redis (see app.module.ts TODO).
   *       Alternatively, use a cache "version" key that is incremented on every
   *       write and embedded in all list cache keys.
   */
  private async invalidateProductListCache(): Promise<void> {
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
  }
}
