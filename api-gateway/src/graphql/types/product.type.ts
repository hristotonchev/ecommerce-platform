import { ObjectType, Field, Int, Float } from '@nestjs/graphql';

@ObjectType()
export class CategoryType {
  @Field(() => Int)
  id: number;

  @Field()
  name: string;

  @Field()
  slug: string;
}

@ObjectType()
export class InventoryType {
  @Field(() => Int)
  quantity: number;

  @Field(() => Int)
  reserved: number;
}

@ObjectType()
export class ProductType {
  @Field(() => Int)
  id: number;

  @Field()
  name: string;

  @Field()
  slug: string;

  @Field({ nullable: true })
  description: string;

  @Field(() => Float)
  price: number;

  @Field()
  is_active: boolean;

  @Field({ nullable: true })
  image_path: string;

  @Field()
  created_at: Date;

  @Field(() => CategoryType, { nullable: true })
  category: CategoryType;

  @Field(() => InventoryType, { nullable: true })
  inventory: InventoryType;
}

@ObjectType()
export class ProductsResult {
  @Field(() => [ProductType])
  data: ProductType[];

  @Field(() => Int)
  total: number;

  @Field(() => Int)
  page: number;

  @Field(() => Int)
  last_page: number;
}
