import { Injectable, ConflictException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
// @ts-ignore
import { User } from '../entities/user.entity';
import * as bcrypt from 'bcryptjs';
import { RegisterDto } from '../auth/dto/register.dto';

@Injectable()
export class UsersService {
    constructor(
        @InjectRepository(User)
        private userRepository: Repository<User>,
    ) {}

    async create(dto: RegisterDto): Promise<User> {
        const exists = await this.userRepository.findOne({
            where: { email: dto.email },
        });
        if (exists) throw new ConflictException('Email already in use');

        const hashed = await bcrypt.hash(dto.password, 12);
        const user = this.userRepository.create({ ...dto, password: hashed });
        return this.userRepository.save(user);
    }

    async findByEmail(email: string): Promise<User | null> {
        return this.userRepository
            .createQueryBuilder('user')
            .addSelect('user.password')
            .where('user.email = :email', { email })
            .getOne();
    }

    async findById(id: number): Promise<User | null> {
        return this.userRepository.findOne({ where: { id } });
    }
}