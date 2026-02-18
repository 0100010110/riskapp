<?php

namespace App\Filament\Resources\Risks\Pages;

use App\Filament\Resources\Risks\RiskResource;
use App\Filament\Resources\Risks\Schemas\RiskForm;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard;

class CreateRisk extends CreateRecord
{
    use HasWizard;

    protected static string $resource = RiskResource::class;

    public function getSteps(): array
    {
        return RiskForm::wizardStepsForCreate();
    }

    public function getWizardComponent(): Component
    {
        return Wizard::make($this->getSteps())
            ->startOnStep($this->getStartStep())
            ->cancelAction($this->getCancelFormAction())
            ->submitAction($this->getSubmitFormAction())
            ->alpineSubmitHandler("\$wire.{$this->getSubmitFormLivewireMethodName()}()")
            ->skippable(false)
            ->contained(false);
    }
}
