import { Controller, Post, Body, HttpCode, HttpStatus } from '@nestjs/common';
import { AuthService } from './auth.service';
import { RegisterDto } from './dto/register.dto';
import { LoginDto } from './dto/login.dto';

// TODO: Add @nestjs/throttler rate-limiting to both endpoints to prevent
//       brute-force attacks.  Recommended limits: 10 req/min on /login and
//       5 req/min on /register per IP.  Store throttle state in Redis so limits
//       are enforced across multiple instances.
@Controller('api/auth')
export class AuthController {
    constructor(private authService: AuthService) {}

    @Post('register')
    register(@Body() dto: RegisterDto) {
        return this.authService.register(dto);
    }

    @Post('login')
    @HttpCode(HttpStatus.OK)
    login(@Body() dto: LoginDto) {
        return this.authService.login(dto);
    }
}