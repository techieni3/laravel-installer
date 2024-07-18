<?php

declare(strict_types=1);

namespace App\Filament\Base;

use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;
use Override;

class CreateRecord extends BaseCreateRecord
{
    #[Override]
    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();

        return $resource::getUrl('index');
    }
}
