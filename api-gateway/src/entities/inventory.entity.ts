import { Entity, PrimaryGeneratedColumn, Column, UpdateDateColumn, OneToOne, JoinColumn } from 'typeorm';

@Entity('inventory')
export class Inventory {
  @PrimaryGeneratedColumn('increment')
  id: number;

  @Column({ unique: true })
  product_id: number;

  @Column({ default: 0 })
  quantity: number;

  @Column({ default: 0 })
  reserved: number;

  @UpdateDateColumn()
  updated_at: Date;
}
