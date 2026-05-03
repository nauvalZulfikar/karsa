<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class AiChatHeroWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-chat-hero-widget';

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
}
