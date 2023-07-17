<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Bellows\PluginSdk\Facades\Value;
use Bellows\PluginSdk\Plugin;
use Bellows\PluginSdk\PluginResults\CanBeInstalled;
use Bellows\PluginSdk\PluginResults\InstallationResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class Valet extends Plugin implements Installable
{
    use CanBeInstalled;

    public function install(): ?InstallationResult
    {
        $urlBase = collect(explode('.', Project::domain()))->slice(0, -1)->implode('.');

        $commands = [];

        if (Console::confirm('Link this directory in Valet?', true)) {
            $commands[] = 'valet link ' . $urlBase;
        }

        if ($secureSite = Console::confirm('Secure this domain with Valet?', true)) {
            $commands[] = 'valet secure ' . $urlBase;
        }

        if (Console::confirm('Isolate PHP version for this project?', true)) {
            $phpVersionsInstalled = Process::run('ls /opt/homebrew/Cellar | grep php@')->output();

            $phpVersionsInstalled = collect(explode("\n", $phpVersionsInstalled))
                ->filter(fn ($version) => $version !== '')
                ->values();

            $phpVersion = Console::choice(
                'Which PHP version?',
                $phpVersionsInstalled->reverse()->values()->toArray(),
                $phpVersionsInstalled->last()
            );

            $commands[] = sprintf('valet isolate %s --site="%s"', $phpVersion, $urlBase);
        }

        $result = InstallationResult::create()->installationCommands(
            collect($commands)->map(fn ($c) => Value::raw($c))->toArray()
        );

        if ($secureSite) {
            $result->environmentVariable(
                'APP_URL',
                Str::of(Project::domain())
                    ->replace('http://', 'https://')
                    ->start('https://')
                    ->toString(),
            );
        }

        return $result;
    }
}
