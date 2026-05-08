import { Module, forwardRef } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { InternalController } from './internal.controller';
import { InternalService } from './internal.service';
import { Order } from '../entities/order.entity';
import { WebsocketsModule } from '../websockets/websockets.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([Order]),
    // forwardRef handles the InternalModule ↔ WebsocketsModule circular import
    // that would occur if WebsocketsModule ever imports InternalModule.
    forwardRef(() => WebsocketsModule),
  ],
  controllers: [InternalController],
  providers: [InternalService],
})
export class InternalModule {}
