<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class RiwayatNotifikasi extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon  = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notifikasi WA';
    protected static ?string $title           = 'Pengaturan Notifikasi WhatsApp';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int    $navigationSort  = 10;
    protected static string  $view            = 'filament.pages.riwayat-notifikasi';
}
