<?php

namespace InlineStudio\MailConnectors;

use Illuminate\Mail\MailManager;
use InlineStudio\MailConnectors\Mailers\O365\Office365Connector;
use InlineStudio\MailConnectors\Mailers\O365\Transport\Office365MailTransport;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MailConnectorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('inlinestudio-mailconnectors')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        parent::packageBooted();
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        $this->app->afterResolving(MailManager::class, function (MailManager $manager) {
            $this->extendMailManager($manager);
        });
    }

    public function extendMailManager(MailManager $manager)
    {
        $manager->extend('O365', function () {
            return new Office365MailTransport(new Office365Connector(
                clientId: config('inlinestudio-mailconnectors.mailers.O365.client_id'),
                clientSecret: config('inlinestudio-mailconnectors.mailers.O365.client_secret'),
                tenant: config('inlinestudio-mailconnectors.mailers.O365.tenant'),
            ));
        });
    }
}
