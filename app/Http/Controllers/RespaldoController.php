<?php

namespace App\Http\Controllers;

use App\Exceptions\RespaldoInvalido;
use App\Models\Actividad;
use App\Models\Ajuste;
use App\Models\User;
use App\Services\Historial;
use App\Services\Respaldo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Respaldos de la base de datos: se descargan al dispositivo del administrador
 * (no se guardan en el servidor) y el superadmin puede restaurarlos.
 */
class RespaldoController extends Controller
{
    public const CLAVE_ULTIMO = 'ultimo_respaldo';

    // A partir de cuántos días sin respaldo se avisa
    public const DIAS_AVISO = 7;

    public function index()
    {
        return view('respaldos.index', [
            'ultimo' => self::ultimo(),
            'historial' => Actividad::where('modulo', 'respaldos')->latest('id')->limit(10)->get(),
            'contenido' => [
                'Usuarios (socios, directiva y plomero)' => DB::table('users')->count(),
                'Lecturas de agua' => DB::table('lecturas')->count(),
                'Multas' => DB::table('multas')->count(),
                'Balances de finanzas' => DB::table('balance_mensuals')->count(),
                'Archivos (QR, comprobantes y fotos)' => DB::table('archivos')->count(),
                'Registros del historial' => DB::table('actividades')->count(),
            ],
            'diasAviso' => self::DIAS_AVISO,
        ]);
    }

    public function descargar(Request $request)
    {
        $request->validate(['password' => 'required|current_password'], [
            'password.current_password' => 'La contraseña no es correcta.',
        ], ['password' => 'contraseña']);

        $usuario = $request->user();
        $nombre = 'respaldo_h2o_'.now()->format('Y-m-d_Hi').'.json.gz';
        $ruta = tempnam(sys_get_temp_dir(), 'h2o');

        $conteos = Respaldo::generar($ruta, $usuario->name);

        Ajuste::guardar(self::CLAVE_ULTIMO, now()->toIso8601String().'|'.$usuario->name);
        Historial::registrar('exportacion', 'respaldos',
            'Descargó un respaldo completo de la base de datos ('.array_sum($conteos).' registros, '.$this->tamano(filesize($ruta)).')');

        return response()->download($ruta, $nombre, ['Content-Type' => 'application/gzip'])->deleteFileAfterSend();
    }

    public function restaurar(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
            'confirmacion' => 'required|in:RESTAURAR',
            'archivo' => 'required|file|max:102400',
        ], [
            'password.current_password' => 'La contraseña no es correcta.',
            'confirmacion.in' => 'Escribe RESTAURAR (en mayúsculas) para confirmar.',
        ], ['password' => 'contraseña', 'archivo' => 'archivo de respaldo']);

        try {
            $datos = Respaldo::leer($request->file('archivo')->getRealPath());
            $restauradas = Respaldo::restaurar($datos);
        } catch (RespaldoInvalido $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        } catch (Throwable $e) {
            // La restauración es una sola transacción: si falla, la base queda como estaba
            report($e);

            return back()->withErrors(['archivo' => 'No se pudo restaurar: el respaldo tiene datos que no encajan en esta base. No se cambió nada.']);
        }

        // Los usuarios se reemplazaron: la sesión actual ya no es válida
        $email = $request->user()->email;
        $nombre = $request->user()->name;
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $fecha = Carbon::parse($datos['creado'])->format('d/m/Y H:i');
        Historial::registrar('restaurado', 'respaldos',
            "Restauró el respaldo del {$fecha} hecho por {$datos['por']} (".array_sum($restauradas).' registros)',
            usuario: User::firstWhere('email', $email),
            nombreInvitado: $nombre,
        );

        return redirect()->route('login')->with('status',
            "Se restauró el respaldo del {$fecha}. Por seguridad se cerró la sesión: vuelve a ingresar.");
    }

    /**
     * Fecha y autor del último respaldo descargado, o null si nunca se hizo.
     *
     * @return array{fecha: Carbon, por: string, dias: int}|null
     */
    public static function ultimo(): ?array
    {
        $valor = Ajuste::valor(self::CLAVE_ULTIMO);
        if (! $valor) {
            return null;
        }

        [$fecha, $por] = array_pad(explode('|', $valor, 2), 2, '');
        $fecha = Carbon::parse($fecha);

        return ['fecha' => $fecha, 'por' => $por, 'dias' => (int) $fecha->copy()->startOfDay()->diffInDays(today())];
    }

    private function tamano(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
    }
}
