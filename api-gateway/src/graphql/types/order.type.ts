import { ObjectType, Field, Int, Float } from '@nestjs/graphql';

@ObjectType()
export class OrderItemType {
  @Field(() => Int)
  id: number;

  @Field(() => Int)
  product_id: number;

  @Field(() => Int)
  quantity: number;

  @Field(() => Float)
  unit_price: number;

  @Field(() => Float)
  subtotal: number;
}

@ObjectType()
export class OrderType {
  @Field(() => Int)
  id: number;

  @Field()
  status: string;

  @Field(() => Float)
  total_amount: number;

  @Field({ nullable: true })
  notes: string;

  @Field()
  created_at: Date;

  @Field(() => [OrderItemType])
  items: OrderItemType[];
}
