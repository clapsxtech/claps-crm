# logs.clapslearn.com

Laravel web application repository for logs.clapslearn.com.

## ERP user guide

Start with the [ERP user guide and journeys for each role](public_html/docs/ERP_USER_GUIDE.md) for login, daily workflows, permissions, handoffs and Sheets transition behavior. The [complete route reference](public_html/docs/ERP_ROUTE_REFERENCE.md) lists browser pages, form actions, exports and all registered Laravel routes.

## Architecture and cutover review

See the [Sheets-to-application ER diagrams and production-readiness review](public_html/docs/architecture-review/README.md) for verified workbook dependencies, schema comparisons, migration join contracts, current release gaps, and validation results (26 October 2026).

The [temporary Sheets bridge](public_html/docs/sheets-transition.md) adds validated, read-only snapshots and a From Sheets workspace, with a separate source-authority switch. It is disabled by default; follow its activation and reconciliation steps before use.

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
