<?php

use App\Livewire\Autenticacion\ConfirmarPassword;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Security;
use Illuminate\Support\Facades\Route;

// Rutas personales de cuenta accesibles para cualquier usuario activo autenticado (ADMINISTRADOR o MARKETING)
Route::middleware(['auth', 'rol:ADMINISTRADOR,MARKETING'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // Editar datos básicos del perfil (nombre y correo)
    Route::get('settings/profile', Profile::class)->name('profile.edit');

    // Preferencia visual del panel (modo claro / modo oscuro)
    Route::get('settings/appearance', Appearance::class)->name('appearance.edit');

    // Pantalla intermedia para reconfirmar contraseña antes de acciones críticas (como activar 2FA)
    Route::get('user/confirm-password', ConfirmarPassword::class)->name('password.confirm');

    // Cambio de contraseña y configuración 2FA (exige middleware 'password.confirm')
    Route::get('settings/security', Security::class)
        ->middleware(['password.confirm'])
        ->name('security.edit');

    // Alias integrados bajo el prefijo administrativo /admin/configuracion/*
    Route::redirect('admin/configuracion', '/admin/configuracion/perfil');
    Route::get('admin/configuracion/perfil', Profile::class)->name('admin.configuracion.perfil');
    Route::get('admin/configuracion/apariencia', Appearance::class)->name('admin.configuracion.apariencia');
    Route::get('admin/configuracion/seguridad', Security::class)->name('admin.configuracion.seguridad');
});
