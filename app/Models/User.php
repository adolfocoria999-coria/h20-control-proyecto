<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RolUsuario;
use App\Models\Concerns\RegistraEnHistorial;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    // SoftDeletes: dar de baja a un socio conserva su historial de lecturas y multas
    use HasFactory, Notifiable, RegistraEnHistorial, SoftDeletes;

    protected array $ignorarEnHistorial = ['email_verified_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'ci',
        'telefono',
        'rol_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    // Relación: Un socio puede tener muchas lecturas de agua
    public function lecturas()
    {
        return $this->hasMany(Lectura::class);
    }

    /**
     * Relación: Un socio tiene muchas multas
     */
    public function multas(): HasMany
    {
        return $this->hasMany(Multa::class, 'user_id');
    }

    /**
     * Solo los usuarios con rol Socio (para formularios de lecturas y multas).
     */
    public function scopeSocios(Builder $query): Builder
    {
        return $query->where('rol_id', RolUsuario::Socio->value);
    }

    public function rolUsuario(): ?RolUsuario
    {
        return $this->rol_id ? RolUsuario::tryFrom((int) $this->rol_id) : null;
    }

    public function esSuperAdmin(): bool
    {
        return $this->rolUsuario() === RolUsuario::SuperAdmin;
    }

    /**
     * Verificar si el usuario es Admin o SuperAdmin
     */
    public function esAdmin(): bool
    {
        return in_array($this->rolUsuario(), [RolUsuario::SuperAdmin, RolUsuario::Admin], true);
    }

    public function esSocio(): bool
    {
        return $this->rolUsuario() === RolUsuario::Socio;
    }

    /**
     * Pantalla de inicio según el rol del usuario.
     */
    public function rutaInicio(): string
    {
        return match (true) {
            $this->esSuperAdmin() => route('usuarios.index'),
            $this->esAdmin() => route('lecturas.index'),
            default => route('socio.consumo'),
        };
    }

    // ---------- Historial ----------

    protected function moduloHistorial(): string
    {
        return 'usuarios';
    }

    protected function etiquetaHistorial(): string
    {
        return "al usuario {$this->name}";
    }

    protected function accionHistorial(string $evento, array $cambios): string
    {
        // Eliminar un usuario es un borrado lógico: queda dado de baja
        return $evento === 'eliminado' && ! $this->isForceDeleting() ? 'baja' : $evento;
    }

    protected function descripcionHistorial(string $accion): string
    {
        $propio = auth()->id() === $this->getKey();

        return match ($accion) {
            'creado' => "Registró al usuario {$this->name} (".RolUsuario::etiqueta($this->rolUsuario()?->alias()).')',
            'actualizado' => $propio
                ? ($this->wasChanged('password') ? 'Cambió su contraseña' : 'Actualizó su perfil')
                : "Actualizó los datos de {$this->name}",
            'baja' => "Dio de baja a {$this->name}",
            'restaurado' => "Restauró a {$this->name}",
            default => "Eliminó definitivamente al usuario {$this->name}",
        };
    }
}
