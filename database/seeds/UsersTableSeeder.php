<?php
declare(strict_types=1);

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'login' => 'john',
        ]);
        DB::table('users')->insert([
            'login' => 'ragez',
        ]);
        DB::table('users')->insert([
            'login' => 'adam',
        ]);
    }
}
