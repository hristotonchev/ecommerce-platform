import { Module } from '@nestjs/common';
import { OrdersGateway } from './orders.gateway';
import { JwtModule } from '@nestjs/jwt';
import { ConfigModule, ConfigService } from '@nestjs/config';

@Module({
  imports: [
    JwtModule.registerAsync({
      imports: [ConfigModule],
      useFactory: (config: ConfigService) => ({
        secret: config.get<string>('JWT_SECRET'),
      }),
      inject: [ConfigService],
    }),
  ],
  providers: [OrdersGateway],
  exports: [OrdersGateway],
})
export class WebsocketsModule {}
