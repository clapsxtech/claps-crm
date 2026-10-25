# Admin LTE 3 with Laravel 8

This is a fresh Laravel 8 project where we work with [AdminLTE 3](https://adminlte.io/). You can clone it and start working on your Laravel 8 project with AdminLTE 3 already implemented.


## How to use

Code for [Laravel 8 + AdminLTE 3]

- Clone the repository with __git clone__
- Copy __.env.example__ file to __.env__
- Edit database credentials in __.env__
- Run __composer install__
- Run __php artisan key:generate__
- Run __php artisan migrate__
- Run `php artisan db:seed`
- Run __php artisan serve__ (if you want to use other port add __--port=90__)
- You can __register__ by clicking on top-right

## License

Feel free to use and re-use any way you want.

## More

- Feel free to send me an e-mail for support [mmnijas@gmail.com](mmnijas@gmail.com).

## Superadmin and staff access

- `/superadmin/login` is the superadmin entry; `/staff/login` is the staff entry. `/login` also remains available for existing accounts, including teachers. The server checks account privileges regardless of the chosen page.
- Active accounts in the configured organization with `user_type=superadmin` have access to all teams (ERP business units). Existing site administrators retain this access. Disabled, deleted, and other-organization accounts are denied.
- In a team's **Team access** page, a superadmin can open **Create or manage staff accounts**, create an account, then grant it the **staff** role in that team. Repeat for each team the person should access. Removing membership revokes that team's access immediately.
- Staff can read and operate within assigned teams, but cannot manage accounts, site settings, roles, team assignments, or unscoped historical reports. Record IDs, exports, lookups, and direct requests are checked on the server.
- Legacy unit `admin` memberships remain team-scoped; they do not confer superadmin privileges. Existing sales and teacher restrictions remain in place. Public account registration is disabled.
- This change uses existing users and membership tables; it requires no new migration and creates no default passwords.
