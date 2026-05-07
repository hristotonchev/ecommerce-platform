import { Resolver, Query, Mutation, Args, Int } from '@nestjs/graphql';
import { UseGuards } from '@nestjs/common';
import { ProductType, ProductsResult } from '../types/product.type';
import { ProductsFilterInput, CreateProductInput } from '../inputs/product.input';
import { ProductsService } from '../../products/products.service';
import { GqlAuthGuard } from '../../auth/guards/gql-auth.guard';
import { GqlRolesGuard } from '../../auth/guards/gql-roles.guard';
import { Roles } from '../../auth/decorators/roles.decorator';
import { UserRole } from '../../entities/user.entity';

@Resolver(() => ProductType)
export class ProductsResolver {
  constructor(private productsService: ProductsService) {}

  @Query(() => ProductsResult)
  async products(
    @Args('filter', { nullable: true }) filter?: ProductsFilterInput,
  ): Promise<ProductsResult> {
    const result = await this.productsService.findAll(filter ?? {}) as any;
    return {
      data:      result.data,
      total:     result.meta.total,
      page:      result.meta.page,
      last_page: result.meta.last_page,
    };
  }

  @Query(() => ProductType, { nullable: true })
  async product(
    @Args('id', { type: () => Int }) id: number,
  ): Promise<ProductType> {
    return this.productsService.findOne(id) as any;
  }

  @Mutation(() => ProductType)
  @UseGuards(GqlAuthGuard, GqlRolesGuard)
  @Roles(UserRole.ADMIN)
  async createProduct(
    @Args('input') input: CreateProductInput,
  ): Promise<ProductType> {
    return this.productsService.create(input) as any;
  }

  @Mutation(() => ProductType)
  @UseGuards(GqlAuthGuard, GqlRolesGuard)
  @Roles(UserRole.ADMIN)
  async deleteProduct(
    @Args('id', { type: () => Int }) id: number,
  ): Promise<ProductType> {
    const product = await this.productsService.findOne(id);
    await this.productsService.remove(id);
    return product as any;
  }
}
