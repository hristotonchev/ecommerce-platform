import { InputType, Field, Int, Float } from '@nestjs/graphql';
import { IsOptional, IsString, IsNumber, IsBoolean, Min } from 'class-validator';

@InputType()
export class ProductsFilterInput {
  @Field(() => Int, { nullable: true })
  @IsOptional()
  page?: number = 1;

  @Field(() => Int, { nullable: true })
  @IsOptional()
  limit?: number = 10;

  @Field({ nullable: true })
  @IsOptional()
  @IsString()
  search?: string;

  @Field(() => Int, { nullable: true })
  @IsOptional()
  category_id?: number;
}

@InputType()
export class CreateProductInput {
  @Field()
  @IsString()
  name: string;

  @Field()
  @IsString()
  description: string;

  @Field(() => Float)
  @IsNumber()
  @Min(0)
  price: number;

  @Field(() => Int)
  @IsNumber()
  category_id: number;

  @Field(() => Int)
  @IsNumber()
  @Min(0)
  quantity: number;
}
