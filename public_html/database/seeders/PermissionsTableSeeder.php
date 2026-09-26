<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            ['name' => 'user.view'],
            ['name' => 'user.create'],
            ['name' => 'user.update'],
            ['name' => 'user.delete'],

            ['name' => 'role.view'],
            ['name' => 'role.create'],
            ['name' => 'role.update'],
            ['name' => 'role.delete'],

            ['name' => 'customer.view'],
            ['name' => 'customer.create'],
            ['name' => 'customer.update'],
            ['name' => 'customer.delete'],

            ['name' => 'product.view'],
            ['name' => 'product.create'],
            ['name' => 'product.update'],
            ['name' => 'product.delete'],

            ['name' => 'purchase.view'],
            ['name' => 'purchase.create'],
            ['name' => 'purchase.update'],
            ['name' => 'purchase.delete'],

            ['name' => 'sell.view'],
            ['name' => 'sell.create'],
            ['name' => 'sell.update'],
            ['name' => 'sell.delete'],

            ['name' => 'service.view'],
            ['name' => 'service.create'],
            ['name' => 'service.update'],
            ['name' => 'service.delete'],

            ['name' => 'bank.view'],
            ['name' => 'bank.create'],
            ['name' => 'bank.update'],
            ['name' => 'bank.delete'],

            ['name' => 'business.view'],
            ['name' => 'business.create'],
            ['name' => 'business.update'],
            ['name' => 'business.delete'],

            ['name' => 'brand.view'],
            ['name' => 'brand.create'],
            ['name' => 'brand.update'],
            ['name' => 'brand.delete'],

            ['name' => 'expense.view'],
            ['name' => 'expense.create'],
            ['name' => 'expense.update'],
            ['name' => 'expense.delete'],

            ['name' => 'tax_rate.view'],
            ['name' => 'tax_rate.create'],
            ['name' => 'tax_rate.update'],
            ['name' => 'tax_rate.delete'],

            ['name' => 'unit.view'],
            ['name' => 'unit.create'],
            ['name' => 'unit.update'],
            ['name' => 'unit.delete'],

            ['name' => 'category.view'],
            ['name' => 'category.create'],
            ['name' => 'category.update'],
            ['name' => 'category.delete'],

            ['name' => 'dashboard.view'],
        ];

        $insert_data = [];
        foreach ($data as $d) {
            $d['guard_name'] = 'web';
            $d['created_at'] = now();
            $d['updated_at'] = now();
            $insert_data[] = $d;
        }
        Permission::insert($insert_data);
    }
}
