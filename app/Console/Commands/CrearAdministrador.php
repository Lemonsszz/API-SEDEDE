<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CrearAdministrador extends Command
{
    protected $signature = 'sedede:crear-admin';

    protected $description = 'Crea una cuenta de administrador de SEDEDE';

    public function handle(): int
    {
        $nombre = $this->ask('Nombre del administrador');
        $correo = $this->ask('Correo electrónico');
        $contrasena = $this->secret('Contraseña');

        if (User::where('email', $correo)->exists()) {
            $this->error('Ya existe un usuario con ese correo.');
            return self::FAILURE;
        }

        User::create([
            'name' => $nombre,
            'email' => $correo,
            'password' => Hash::make($contrasena),
        ]);

        $this->info('Administrador creado correctamente.');

        return self::SUCCESS;
    }
}
