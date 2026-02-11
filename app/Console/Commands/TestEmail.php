<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to leehongjie91@gmail.com to verify mail configuration';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $to = 'leehongjie91@gmail.com';
        $this->info("Sending test email to {$to}...");

        try {
            Mail::raw('This is a test email from the production server to verify SMTP/Mail settings.', function ($message) use ($to) {
                $message->to($to)
                    ->subject('Test Email from Production Server');
            });

            $this->info('Test email sent successfully!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
