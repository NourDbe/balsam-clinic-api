<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-monthly-reports')]
#[Description('Command description')]
class GenerateMonthlyReports extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
