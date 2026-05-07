import { Resolver, Query, Args, Int, Context } from '@nestjs/graphql';
import { UseGuards } from '@nestjs/common';
import { OrderType } from '../types/order.type';
import { OrdersService } from '../../orders/orders.service';
import { GqlAuthGuard } from '../../auth/guards/gql-auth.guard';
import { GqlRolesGuard } from '../../auth/guards/gql-roles.guard';
import { Roles } from '../../auth/decorators/roles.decorator';
import { UserRole } from '../../entities/user.entity';

@Resolver(() => OrderType)
export class OrdersResolver {
  constructor(private ordersService: OrdersService) {}

  @Query(() => [OrderType])
  @UseGuards(GqlAuthGuard)
  async myOrders(@Context() ctx: any): Promise<OrderType[]> {
    return this.ordersService.findUserOrders(ctx.req.user.id) as any;
  }

  @Query(() => OrderType, { nullable: true })
  @UseGuards(GqlAuthGuard)
  async order(
    @Args('id', { type: () => Int }) id: number,
    @Context() ctx: any,
  ): Promise<OrderType> {
    const userId = ctx.req.user.role === UserRole.ADMIN
      ? undefined
      : ctx.req.user.id;
    return this.ordersService.findOne(id, userId) as any;
  }

  @Query(() => [OrderType])
  @UseGuards(GqlAuthGuard, GqlRolesGuard)
  @Roles(UserRole.ADMIN)
  async allOrders(): Promise<OrderType[]> {
    const result = await this.ordersService.findAll(1, 50);
    return result.data as any;
  }
}
