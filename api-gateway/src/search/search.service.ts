import { Injectable, OnModuleInit, Logger } from '@nestjs/common';
import { ElasticsearchService } from '@nestjs/elasticsearch';

const INDEX = 'products';

@Injectable()
export class SearchService implements OnModuleInit {
  private readonly logger = new Logger(SearchService.name);

  constructor(private elastic: ElasticsearchService) {}

  async onModuleInit() {
    await this.ensureIndex();
  }

  private async ensureIndex() {
    try {
      const exists = await this.elastic.indices.exists({ index: INDEX });
      if (!exists) {
        await this.elastic.indices.create({
          index: INDEX,
          mappings: {
            properties: {
              id:          { type: 'integer' },
              name:        { type: 'text', analyzer: 'standard',
                             fields: { keyword: { type: 'keyword' } } },
              description: { type: 'text', analyzer: 'standard' },
              price:       { type: 'float' },
              category_id: { type: 'integer' },
              category:    { type: 'keyword' },
              is_active:   { type: 'boolean' },
              quantity:    { type: 'integer' },
              created_at:  { type: 'date' },
            },
          },
        });
        this.logger.log('Products index created');
      }
    } catch (err) {
      this.logger.warn('Could not create index: ' + err.message);
    }
  }

  async indexProduct(product: any) {
    try {
      await this.elastic.index({
        index: INDEX,
        id:    String(product.id),
        document: {
          id:          product.id,
          name:        product.name,
          description: product.description,
          price:       Number(product.price),
          category_id: product.category_id,
          category:    product.category?.name ?? '',
          is_active:   product.is_active,
          quantity:    product.inventory?.quantity ?? 0,
          created_at:  product.created_at,
        },
      });
    } catch (err) {
      this.logger.warn('Index failed: ' + err.message);
    }
  }

  async removeProduct(id: number) {
    try {
      await this.elastic.delete({ index: INDEX, id: String(id) });
    } catch (err) {
      this.logger.warn('Remove from index failed: ' + err.message);
    }
  }

  async search(query: string, filters: {
    category_id?: number;
    min_price?:   number;
    max_price?:   number;
    page?:        number;
    limit?:       number;
  } = {}) {
    const { category_id, min_price, max_price, page = 1, limit = 10 } = filters;

    const must: any[]   = [{ term: { is_active: true } }];
    const filter: any[] = [];

    if (query) {
      must.push({
        multi_match: {
          query,
          fields:    ['name^3', 'description', 'category'],
          fuzziness: 'AUTO',
        },
      });
    }

    if (category_id) filter.push({ term: { category_id } });

    if (min_price !== undefined || max_price !== undefined) {
      const range: any = {};
      if (min_price !== undefined) range.gte = min_price;
      if (max_price !== undefined) range.lte = max_price;
      filter.push({ range: { price: range } });
    }

    const result = await this.elastic.search({
      index: INDEX,
      from:  (page - 1) * limit,
      size:  limit,
      query: { bool: { must, filter } },
      sort:  [
        { _score:      { order: 'desc' } } as any,
        { created_at:  { order: 'desc' } },
      ],
      highlight: {
        fields: {
          name:        { pre_tags: ['<mark>'], post_tags: ['</mark>'] },
          description: {
            pre_tags:           ['<mark>'],
            post_tags:          ['</mark>'],
            fragment_size:      150,
            number_of_fragments: 1,
          },
        },
      },
      aggs: {
        price_stats: { stats: { field: 'price' } },
        by_category: { terms: { field: 'category', size: 20 } },
      },
    });

    const hits  = result.hits.hits;
    const total = typeof result.hits.total === 'number'
      ? result.hits.total
      : (result.hits.total as any)?.value ?? 0;

    return {
      data: hits.map((h: any) => ({
        ...h._source,
        score:     h._score,
        highlight: h.highlight ?? {},
      })),
      meta: {
        total,
        page,
        limit,
        last_page: Math.ceil(total / limit),
      },
      aggregations: {
        price_stats: (result.aggregations?.price_stats as any) ?? {},
        categories:  (result.aggregations?.by_category as any)?.buckets ?? [],
      },
    };
  }

  async bulkIndex(products: any[]) {
    if (!products.length) return;

    const operations = products.flatMap((p) => [
      { index: { _index: INDEX, _id: String(p.id) } },
      {
        id:          p.id,
        name:        p.name,
        description: p.description,
        price:       Number(p.price),
        category_id: p.category_id,
        category:    p.category?.name ?? '',
        is_active:   p.is_active,
        quantity:    p.inventory?.quantity ?? 0,
        created_at:  p.created_at,
      },
    ]);

    await this.elastic.bulk({ operations, refresh: true });
    this.logger.log(`Bulk indexed ${products.length} products`);
  }
}
