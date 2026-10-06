<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Console\Commands;

use Illuminate\Console\Command;

class FormStepperCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'form-stepper:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package form-stepper.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('FormStepper placeholder command executed.');

        return self::SUCCESS;
    }
}
