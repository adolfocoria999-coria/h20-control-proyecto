<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\Rol;
use App\Models\User;
use App\Services\CsvExporter;
use App\Services\Historial;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public const POR_PAGINA = 20;

    // Lista filtrada y paginada en el servidor (por nombre, CI o teléfono)
    public function index(Request $request)
    {
        $usuarios = $this->consultaFiltrada($request)
            ->with('rol')
            ->orderBy('name')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('usuarios.index', compact('usuarios'));
    }

    /**
     * Filtros de la lista: nombre o email, C.I. y teléfono.
     */
    private function consultaFiltrada(Request $request): Builder
    {
        $filtros = array_map(fn ($v) => trim((string) $v), $request->only(['nombre', 'ci', 'telefono']));

        return User::query()
            ->when($filtros['nombre'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")))
            ->when($filtros['ci'] ?? null, fn ($q, $v) => $q->where('ci', 'like', "%{$v}%"))
            ->when($filtros['telefono'] ?? null, fn ($q, $v) => $q->where('telefono', 'like', "%{$v}%"));
    }

    public function create()
    {
        $roles = Rol::all();

        return view('usuarios.create', compact('roles'));
    }

    // La contraseña se encripta sola gracias al cast 'hashed' del modelo
    public function store(UsuarioRequest $request)
    {
        // La contraseña la eligió el superadmin: el socio debe cambiarla al ingresar
        $socio = new User($request->validated());
        $socio->debe_cambiar_clave = true;
        $socio->save();

        return redirect()->route('usuarios.index')->with('success', 'Socio registrado con éxito.');
    }

    public function edit(User $usuario)
    {
        $roles = Rol::all();

        return view('usuarios.edit', compact('usuario', 'roles'));
    }

    public function update(UsuarioRequest $request, User $usuario)
    {
        if ($usuario->is($request->user()) && (int) $request->rol_id !== (int) $usuario->rol_id) {
            return back()->withInput()->with('error', 'No puedes cambiar tu propio rol.');
        }

        $datos = $request->validated();

        // Contraseña vacía = conservar la actual
        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        $usuario->fill($datos);
        // Una contraseña puesta por el superadmin a otro usuario es temporal
        if (isset($datos['password']) && ! $usuario->is($request->user())) {
            $usuario->debe_cambiar_clave = true;
        }
        $usuario->save();

        return redirect()->route('usuarios.index')->with('success', 'Socio actualizado con éxito.');
    }

    /**
     * Genera una contraseña temporal para quien la olvidó. Se muestra una sola vez
     * al superadmin (con un enlace para enviarla por WhatsApp) y el socio debe
     * cambiarla al ingresar. Cierra las sesiones abiertas de ese usuario.
     */
    public function restablecerClave(Request $request, User $usuario)
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'Para cambiar tu propia contraseña usa "Mi Perfil".');
        }

        $clave = 'H2O-'.random_int(1000, 9999).'-'.random_int(1000, 9999);

        $usuario->password = $clave;
        $usuario->debe_cambiar_clave = true;
        $usuario->setRememberToken(null);
        $usuario->saveQuietly();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
        }

        Historial::registrar('actualizado', 'usuarios', "Restableció la contraseña de {$usuario->name}", $usuario);

        $mensaje = "Hola {$usuario->name}. Tu contraseña temporal de H2O Control (".config('otb.nombre')."): {$clave}\n"
            ."Ingresa con tu correo {$usuario->email} en ".url('/login').' y elige una nueva contraseña.';

        return redirect()->route('usuarios.index')->with('clave_temporal', [
            'nombre' => $usuario->name,
            'email' => $usuario->email,
            'clave' => $clave,
            'whatsapp' => WhatsApp::enlace($usuario->telefono, $mensaje),
        ]);
    }

    public function destroy(User $usuario)
    {
        if ($usuario->is(auth()->user())) {
            return redirect()->route('usuarios.index')->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('success', 'Socio dado de baja. Su historial de lecturas y multas se conserva.');
    }

    // Exporta la lista completa de socios a CSV / Excel
    // Exporta lo mismo que muestra la lista (respeta los filtros)
    public function exportar(Request $request)
    {
        $filas = $this->consultaFiltrada($request)->with('rol')->orderBy('name')->lazy()->map(fn (User $socio) => [
            $socio->id,
            $socio->name,
            $socio->email,
            $socio->ci ?? 'N/A',
            $socio->telefono ?? 'N/A',
            $socio->rol->nombre ?? 'Sin Rol',
        ]);

        Historial::registrar('exportacion', 'usuarios', 'Exportó la lista de socios a Excel');

        return CsvExporter::descargar(
            'lista_socios_'.date('Y-m-d_H-i').'.csv',
            ['ID', 'Nombre Completo', 'Email', 'Carnet de Identidad', 'Telefono', 'Rol'],
            $filas
        );
    }
}
