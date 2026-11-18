<?php

declare(strict_types=1);

namespace App\Filament\Shared;

use App\Support\Brand;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class WorkspaceAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = mb_substr(trim(Filament::getNameForDefaultAvatar($record)), 0, 1);
        $text = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 44 44"><text x="22" y="23" dominant-baseline="middle" text-anchor="middle" font-family="'.Brand::svgFontFamily().'" font-size="17" font-weight="600">'.$text.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
