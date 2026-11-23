<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:lift-patient-bans')]
#[Description('Command description')]
class LiftPatientBans extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
