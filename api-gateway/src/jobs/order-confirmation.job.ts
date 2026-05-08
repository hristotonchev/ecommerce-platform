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

// TODO: Replace this in-process async job with a proper persistent queue.
//       Recommended path: add @nestjs/bullmq + Redis so emails survive restarts,
//       failed jobs are retried with exponential back-off, and the queue depth
//       is observable in Bull Dashboard.
//
// TODO: Integrate a real transactional email provider:
//         - AWS SES  → use @aws-sdk/client-ses
//         - SendGrid → use @sendgrid/mail
//         - Nodemailer + SMTP relay (e.g. Mailgun, Postmark)
//       Render the email from a Handlebars / Mjml template so the content is
//       easy to update without touching TypeScript.
export class OrderConfirmationJob {
  private static readonly logger = new Logger('EmailQueue');

  static async process(payload: OrderConfirmationPayload): Promise<void> {
    this.logger.log(
      `[Queue] Processing order confirmation email for order #${payload.orderId}`
    );

    // Simulate async email processing (replace with real provider call above)
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
