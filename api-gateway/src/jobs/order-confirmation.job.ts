import { Logger } from '@nestjs/common';

export interface OrderConfirmationPayload {
  orderId:     number;
  userEmail:   string;
  userName:    string;
  totalAmount: number;
  items:       Array<{
    productName: string;
    quantity:    number;
    unitPrice:   number;
    subtotal:    number;
  }>;
}

export class OrderConfirmationJob {
  private static readonly logger = new Logger('EmailQueue');

  static async process(payload: OrderConfirmationPayload): Promise<void> {
    // In production: use nodemailer, SendGrid, AWS SES etc.
    // For now: mock implementation with realistic logging

    this.logger.log(
      `[Queue] Processing order confirmation email for order #${payload.orderId}`
    );

    // Simulate email processing delay
    await new Promise(resolve => setTimeout(resolve, 100));

    const itemsList = payload.items
      .map(i => `  - ${i.productName} x${i.quantity} @ $${i.unitPrice} = $${i.subtotal}`)
      .join('\n');

    this.logger.log(`
====================================
  ORDER CONFIRMATION EMAIL (MOCK)
====================================
  To:      ${payload.userEmail}
  Subject: Order #${payload.orderId} Confirmed!

  Hi ${payload.userName},

  Your order has been confirmed!

  Items:
${itemsList}

  Total: $${payload.totalAmount}

  Thank you for your purchase!
====================================
    `);
  }
}
