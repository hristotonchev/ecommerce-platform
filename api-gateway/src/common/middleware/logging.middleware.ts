import { Injectable, NestMiddleware, Logger } from '@nestjs/common';
import { Request, Response, NextFunction } from 'express';

@Injectable()
export class LoggingMiddleware implements NestMiddleware {
  private readonly logger = new Logger('HTTP');

  use(req: Request, res: Response, next: NextFunction): void {
    const { method, originalUrl, ip } = req;
    const userAgent = req.get('user-agent') || '';
    const start = Date.now();

    res.on('finish', () => {
      const { statusCode } = res;
      const duration = Date.now() - start;
      const userId = (req as any).user?.id ?? 'guest';

      this.logger.log(
        `${method} ${originalUrl} ${statusCode} ${duration}ms — IP: ${ip} User: ${userId} Agent: ${userAgent}`,
      );
    });

    next();
  }
}
