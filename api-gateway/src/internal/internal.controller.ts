import {
  Controller, Post, Body,
  Param, ParseIntPipe, UseGuards,
} from '@nestjs/common';
import { InternalService } from './internal.service';
import { ApiKeyGuard } from '../auth/guards/api-key.guard';

@Controller('api/internal')
@UseGuards(ApiKeyGuard)
export class InternalController {

  constructor(private internalService: InternalService) {}

  @Post('cache/invalidate')
  invalidateCache(@Body() body: { type: string; id: number }) {
    return this.internalService.invalidateCache(body.type, body.id);
  }

  @Post('orders/:id/status')
  updateOrderStatus(
    @Param('id', ParseIntPipe) id: number,
    @Body('status') status: string,
  ) {
    return this.internalService.updateOrderStatus(id, status);
  }
}
