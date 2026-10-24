# Household Finance - Backend API

Premium household finance management application backend built with Laravel 12.

## 🚀 Features

- **Multi-user Family System** - Manage finances with family members
- **Account Management** - Support for cash, bank, e-wallet, and credit cards
- **Transaction Tracking** - Income, expense, and transfer transactions
- **Budget Management** - Set and track budgets per category
- **Recurring Bills** - Manage recurring payments
- **Dashboard Analytics** - Real-time financial insights
- **Category System** - Hierarchical categories with custom support
- **RESTful API** - Clean and well-documented endpoints

## 📋 Requirements

- PHP >= 8.3
- Composer
- MySQL >= 8.0
- Laravel 12

## 🛠️ Installation

1. **Install Dependencies**
```bash
composer install
```

2. **Environment Setup**
```bash
cp .env.example .env
```

Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=household_finance
DB_USERNAME=root
DB_PASSWORD=
```

3. **Generate Application Key**
```bash
php artisan key:generate
```

4. **Run Migrations**
```bash
php artisan migrate
```

5. **Seed Database**
```bash
php artisan db:seed --class=CategorySeeder
```

6. **Start Development Server**
```bash
php artisan serve
```

API will be available at: `http://localhost:8000`

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api/v1
```

### Authentication Endpoints

#### Register
```http
POST /auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "081234567890"
}
```

#### Login
```http
POST /auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

Response:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {...},
    "token": "1|abc123..."
  }
}
```

### Protected Endpoints (Require Bearer Token)

#### Get Profile
```http
GET /me
Authorization: Bearer {token}
```

#### Create Family
```http
POST /families
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "My Family",
  "currency": "IDR"
}
```

#### Get Accounts
```http
GET /accounts?family_id=1
Authorization: Bearer {token}
```

#### Create Transaction
```http
POST /transactions
Authorization: Bearer {token}
Content-Type: application/json

{
  "family_id": 1,
  "account_id": 1,
  "type": "expense",
  "amount": 50000,
  "category_id": 1,
  "date": "2024-12-13",
  "note": "Lunch"
}
```

#### Dashboard Summary
```http
GET /dashboard/summary?family_id=1&period=month
Authorization: Bearer {token}
```

## 🗄️ Database Schema

### Tables
- `users` - User accounts
- `families` - Family groups
- `family_user` - Family membership
- `accounts` - Financial accounts
- `categories` - Transaction categories
- `transactions` - Financial transactions
- `budgets` - Budget tracking
- `bills` - Recurring bills

## 🔒 Security

- Laravel Sanctum for API authentication
- Password hashing with bcrypt
- CSRF protection
- SQL injection prevention via Eloquent ORM
- Input validation on all endpoints

## 📁 Project Structure

```
app/
├── Http/
│   └── Controllers/
│       └── Api/
│           └── V1/
│               ├── AuthController.php
│               ├── FamilyController.php
│               ├── AccountController.php
│               ├── CategoryController.php
│               ├── TransactionController.php
│               └── DashboardController.php
├── Models/
│   ├── User.php
│   ├── Family.php
│   ├── Account.php
│   ├── Category.php
│   ├── Transaction.php
│   ├── Budget.php
│   └── Bill.php
database/
├── migrations/
└── seeders/
    └── CategorySeeder.php
routes/
└── api.php
```

## 🧪 Testing

```bash
php artisan test
```

## 📝 License

This project is private and proprietary.

## 👨‍💻 Developer

Built with ❤️ using Laravel 12
