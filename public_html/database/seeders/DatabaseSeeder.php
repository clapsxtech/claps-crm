<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        DB::table('settings')->truncate();
        DB::table('settings')->insert([
            'id'=>1,
            'name'=>'Zencare Inc',
            'address'=>'Abc 673585',
            'phone'=>'8123456789',
            'support_mail'=>'Abc 673585',
            'admin_mail'=>'Abc 673585',
            'career_mail'=>'Abc 673585',
            'facebook'=>'#',
            'instagram'=>'#',
            'youtube'=>'#',
            'linkedin'=>'#',
            'twitter'=>'#',
            'map'=>'#',
            'created_at'=>now(),
            'updated_at'=>now()
        ]);
        DB::table('users')->truncate();
        DB::table('users')->insert([
            'id'=>1,
            'business_id'=>1,
            'name'=>'Admin',
            'email'=>'admin@admin.com',
            'phone'=>'8123456789',
            'profile_photo'=>'img/user2.png',
            'password'=>Hash::make('password'),
            'created_at'=>now(),
            'updated_at'=>now()
        ]);
        // $this->call([PermissionsTableSeeder::class,
        //             CurrenciesTableSeeder::class
        //             ]);
    }
}
