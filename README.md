# logs.clapslearn.com

Laravel web application repository for logs.clapslearn.com.

## Project Structure

The project files reside inside the `public_html` directory:
- `public_html/` — Core Laravel application codebase

## Setup

1. Navigate to the `public_html` directory:
   ```bash
   cd public_html
   ```
2. Install dependencies:
   ```bash
   composer install
   npm install
   ```
3. Configure environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Run migrations:
   ```bash
   php artisan migrate
   ```
