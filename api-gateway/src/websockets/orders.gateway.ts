import {
  WebSocketGateway,
  WebSocketServer,
  SubscribeMessage,
  MessageBody,
  ConnectedSocket,
  OnGatewayConnection,
  OnGatewayDisconnect,
} from '@nestjs/websockets';
import { Server, Socket } from 'socket.io';
import { JwtService } from '@nestjs/jwt';
import { ConfigService } from '@nestjs/config';

// TODO: Lock down the CORS origin list before going to production.
//       `origin: '*'` is fine for local dev but allows any domain to connect
//       to the WebSocket namespace.  Read the allowed origins from config
//       (e.g. process.env.CORS_ORIGINS) and pass an array or a validator fn.
@WebSocketGateway({
  cors: { origin: '*' },
  namespace: '/orders',
})
export class OrdersGateway implements OnGatewayConnection, OnGatewayDisconnect {
  @WebSocketServer()
  server: Server;

  private connectedClients = new Map<string, { userId: number; socket: Socket }>();

  constructor(
    private jwtService: JwtService,
    private config: ConfigService,
  ) {}

  async handleConnection(socket: Socket) {
    try {
      const token = socket.handshake.auth?.token ||
                    socket.handshake.headers?.authorization?.replace('Bearer ', '');

      if (!token) {
        socket.disconnect();
        return;
      }

      const payload = this.jwtService.verify(token, {
        secret: this.config.get('JWT_SECRET'),
      });

      this.connectedClients.set(socket.id, {
        userId: payload.sub,
        socket,
      });

      // Join user-specific room
      socket.join(`user_${payload.sub}`);
      socket.emit('connected', { message: 'Connected to order updates' });

      console.log(`[WS] User #${payload.sub} connected`);
    } catch {
      socket.disconnect();
    }
  }

  handleDisconnect(socket: Socket) {
    const client = this.connectedClients.get(socket.id);
    if (client) {
      console.log(`[WS] User #${client.userId} disconnected`);
      this.connectedClients.delete(socket.id);
    }
  }

  // Push order update to specific user
  notifyOrderUpdate(userId: number, order: any) {
    this.server.to(`user_${userId}`).emit('order_updated', {
      orderId: order.id,
      status:  order.status,
      message: `Your order #${order.id} is now ${order.status}`,
    });
    console.log(`[WS] Notified user #${userId} about order #${order.id}`);
  }

  // Admin broadcast — all connected clients
  broadcastOrderCreated(order: any) {
    this.server.emit('order_created', {
      orderId:     order.id,
      totalAmount: order.total_amount,
    });
  }

  @SubscribeMessage('ping')
  handlePing(@ConnectedSocket() socket: Socket) {
    socket.emit('pong', { timestamp: new Date().toISOString() });
  }
}
