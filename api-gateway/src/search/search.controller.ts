import { Controller, Get, Query } from '@nestjs/common';
import { SearchService } from './search.service';
import { ProductsService } from '../products/products.service';

@Controller('api/search')
export class SearchController {
  constructor(
    private searchService: SearchService,
    private productsService: ProductsService,
  ) {}

  @Get('products')
  async searchProducts(
    @Query('q') q: string,
    @Query('category_id') category_id?: string,
    @Query('min_price') min_price?: string,
    @Query('max_price') max_price?: string,
    @Query('page') page?: string,
    @Query('limit') limit?: string,
  ) {
    return this.searchService.search(q ?? '', {
      category_id: category_id ? parseInt(category_id) : undefined,
      min_price:   min_price   ? parseFloat(min_price) : undefined,
      max_price:   max_price   ? parseFloat(max_price) : undefined,
      page:        page        ? parseInt(page) : 1,
      limit:       limit       ? parseInt(limit) : 10,
    });
  }

  @Get('reindex')
  async reindex() {
    const result = await this.productsService.findAll({ page: 1, limit: 1000 }) as any;
    await this.searchService.bulkIndex(result.data);
    return { indexed: result.data.length, message: 'Reindex complete' };
  }
}
