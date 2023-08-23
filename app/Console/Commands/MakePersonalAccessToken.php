<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakePersonalAccessToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passport:personal-access-token {userId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate personal access token for user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('userId');

        $user = User::find($userId);

        if (!$user) {
            $this->error('User not found');
            return;
        }

        $token = $user->createToken('Personal Access Token')->accessToken;
        $user->access_token = $token;
        $user->save();

        $this->info('Token created successfully');
        $this->info('Token: ' . $token);
    }
}
