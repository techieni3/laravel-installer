<?php

declare(strict_types=1);

namespace App\Filament\Base;

use Filament\Resources\Pages\EditRecord as BaseEditRecord;
use Override;

class EditRecord extends BaseEditRecord
{
    #[Override]
    protected function getRedirectUrl(): ?string
    {
        $resource = static::getResource();

        return $resource::getUrl('index');
    }
}
