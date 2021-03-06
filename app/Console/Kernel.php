<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \Laravel\Passport\Console\KeysCommand::class,
        Commands\SaltRandomCommand::class,
        Commands\BsInstallCommand::class,
        Commands\PluginEnableCommand::class,
        Commands\PluginDisableCommand::class,
        Commands\OptionsCacheCommand::class,
    ];
}
