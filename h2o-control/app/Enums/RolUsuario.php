<?php

namespace App\Enums;

/**
 * Roles del sistema. Los valores coinciden con los IDs de la tabla `rols`
 * creados por RolSeeder.
 */
enum RolUsuario: int
{
    case SuperAdmin = 1;
    case Admin = 2;
    case Socio = 3;

    // Alias corto: superadmin, admin, socio (middleware `role:` e historial)
    public function alias(): string
    {
        return match ($this) {
            self::SuperAdmin => 'superadmin',
            self::Admin => 'admin',
            self::Socio => 'socio',
        };
    }

    public static function etiqueta(?string $alias): string
    {
        return match ($alias) {
            'superadmin' => 'Superadministrador',
            'admin' => 'Administrador',
            'socio' => 'Socio',
            default => 'Sin rol',
        };
    }

    /**
     * Resuelve el alias usado en el middleware `role:` (superadmin, admin, socio).
     */
    public static function desdeAlias(string $alias): self
    {
        return match (strtolower($alias)) {
            'superadmin' => self::SuperAdmin,
            'admin' => self::Admin,
            'socio' => self::Socio,
        };
    }
}
