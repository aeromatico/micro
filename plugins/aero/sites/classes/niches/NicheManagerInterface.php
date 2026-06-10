<?php namespace Aero\Sites\Classes\Niches;

interface NicheManagerInterface
{
    public function getHandle(): string;
    public function getName(): string;
    public function getIcon(): string;
    public function getFeatures(): array;
    public function getDefaultPages(): array;
    public function getSeoDefaults(): array;
    public function getContactDefaults(): array;
    public function getRecommendedNotification(): string;
    public function provision(\Aero\Sites\Models\Tenant $tenant): void;
}
