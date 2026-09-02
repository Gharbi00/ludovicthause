<?php

namespace App\Services\Rse;

class ConfigurationChauffeur
{
    public function __construct(
        public readonly int $nbChauffeurs,
        public readonly int $nbNuitees,
        public readonly int $nbJours,
        public readonly int $conduiteParJourMinutes,
        public readonly array $details = [],
    ) {
    }
}
