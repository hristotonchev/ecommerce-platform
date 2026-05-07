import { Injectable, UnauthorizedException } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { UsersService } from '../users/users.service';
import { RegisterDto } from './dto/register.dto';
import { LoginDto } from './dto/login.dto';
import * as bcrypt from 'bcryptjs';

@Injectable()
export class AuthService {
    constructor(
        private usersService: UsersService,
        private jwtService: JwtService,
    ) {}

    async register(dto: RegisterDto) {
        const user = await this.usersService.create(dto);
        const token = this.generateToken(user.id, user.email, user.role);
        return { user: { id: user.id, name: user.name, email: user.email, role: user.role }, ...token };
    }

    async login(dto: LoginDto) {
        const user = await this.usersService.findByEmail(dto.email);
        if (!user) throw new UnauthorizedException('Invalid credentials');

        const valid = await bcrypt.compare(dto.password, user.password);
        if (!valid) throw new UnauthorizedException('Invalid credentials');

        const token = this.generateToken(user.id, user.email, user.role);
        return { user: { id: user.id, name: user.name, email: user.email, role: user.role }, ...token };
    }

    private generateToken(userId: number, email: string, role: string) {
        const payload = { sub: userId, email, role };
        return {
            access_token: this.jwtService.sign(payload),
        };
    }
}