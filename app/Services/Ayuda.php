<?php

namespace App\Services;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Asistente de Ayuda: responde cómo usar el sistema SOLO con el manual
 * (resources/ayuda/manual.php), según el rol de quien pregunta.
 *
 * 1. Busca en el manual las secciones que mejor coinciden con la pregunta.
 * 2. Si hay una IA configurada (Groq, plan gratuito), redacta la respuesta con
 *    esas secciones; si no hay o falla, responde con las secciones tal cual.
 * Nunca se envían datos personales: solo la pregunta, el rol y el manual.
 */
class Ayuda
{
    public const MAX_SECCIONES = 4;

    // Palabras que no ayudan a encontrar la sección
    private const VACIAS = ['como', 'que', 'donde', 'cuando', 'cual', 'quien', 'para', 'por', 'con', 'una', 'uno', 'los', 'las',
        'del', 'el', 'la', 'de', 'en', 'y', 'o', 'a', 'mi', 'mis', 'me', 'se', 'es', 'un', 'lo', 'al', 'si', 'no', 'puedo', 'hago',
        'hacer', 'quiero', 'tengo', 'hay', 'esta', 'este', 'eso', 'esto', 'su', 'sus', 'tu', 'tus', 'le', 'les', 'ya', 'muy', 'mas'];

    /**
     * Secciones del manual que puede ver este rol.
     *
     * @return list<array{titulo: string, roles: list<string>, claves: string, texto: string}>
     */
    public static function manual(?RolUsuario $rol): array
    {
        $alias = $rol?->alias() ?? 'socio';

        return array_values(array_filter(
            require resource_path('ayuda/manual.php'),
            fn ($s) => in_array('todos', $s['roles'], true) || in_array($alias, $s['roles'], true)
        ));
    }

    /**
     * Las secciones que mejor responden la pregunta (de mayor a menor coincidencia).
     */
    public static function buscar(string $pregunta, ?RolUsuario $rol, int $cantidad = self::MAX_SECCIONES): array
    {
        $palabras = self::palabras($pregunta);
        if (! $palabras) {
            return [];
        }

        $puntajes = [];
        foreach (self::manual($rol) as $i => $seccion) {
            $titulo = self::palabras($seccion['titulo']);
            $claves = self::palabras($seccion['claves']);
            $texto = self::palabras($seccion['texto']);
            $puntaje = 0;

            foreach ($palabras as $p) {
                // Coincidencia exacta o por raíz ("pague" ~ "pagar", "recibos" ~ "recibo")
                $coincide = fn (array $lista) => count(array_filter($lista, fn ($w) => $w === $p || (strlen($p) >= 4 && strlen($w) >= 4 && substr($w, 0, 4) === substr($p, 0, 4))));
                $puntaje += 3 * min(1, $coincide($titulo)) + 2 * min(1, $coincide($claves)) + min(1, $coincide($texto));
            }

            if ($puntaje > 0) {
                $puntajes[$i] = [$puntaje, $seccion];
            }
        }

        uasort($puntajes, fn ($a, $b) => $b[0] <=> $a[0]);

        return array_map(fn ($p) => $p[1], array_slice($puntajes, 0, $cantidad));
    }

    /**
     * @param  list<array{rol: string, texto: string}>  $conversacion  mensajes previos (usuario/asistente)
     * @return array{respuesta: string, fuentes: list<string>, modo: string}
     */
    public static function responder(string $pregunta, User $usuario, array $conversacion = []): array
    {
        $rol = $usuario->rolUsuario();
        $secciones = self::buscar($pregunta.' '.implode(' ', array_column(array_slice($conversacion, -2), 'texto')), $rol);
        $fuentes = array_column($secciones, 'titulo');

        if (config('services.groq.key')) {
            try {
                return ['respuesta' => self::preguntarIa($pregunta, $rol, $secciones, $conversacion), 'fuentes' => $fuentes, 'modo' => 'ia'];
            } catch (Throwable $e) {
                report($e); // sin IA disponible: se responde con el manual
            }
        }

        if (! $secciones) {
            return [
                'respuesta' => 'No encontré eso en el manual. Prueba con otras palabras (por ejemplo: "pagar con QR", "ver mi recibo", "cargar lecturas") o consulta a Hacienda: '.config('otb.telefono_finanzas').'.',
                'fuentes' => [],
                'modo' => 'manual',
            ];
        }

        $principal = array_shift($secciones);
        $respuesta = "**{$principal['titulo']}**\n{$principal['texto']}";
        if ($secciones) {
            $respuesta .= "\n\nTambién te puede servir: ".implode(', ', array_column($secciones, 'titulo')).'.';
        }

        return ['respuesta' => $respuesta, 'fuentes' => $fuentes, 'modo' => 'manual'];
    }

    private static function preguntarIa(string $pregunta, ?RolUsuario $rol, array $secciones, array $conversacion): string
    {
        $rolNombre = RolUsuario::etiqueta($rol?->alias() ?? 'socio');
        $manual = $secciones
            ? implode("\n\n", array_map(fn ($s) => "### {$s['titulo']}\n{$s['texto']}", $secciones))
            : '(No hay secciones del manual relacionadas con esta pregunta.)';

        $sistema = implode("\n", [
            'Eres el asistente de ayuda de "H2O Control", el sistema de agua potable de la '.config('otb.nombre').' (Bolivia).',
            "Quien pregunta tiene el rol: {$rolNombre}.",
            'Responde en español, breve y claro, con pasos numerados cuando sea un procedimiento. Usa **negrita** para nombres de botones y menús.',
            'Usa ÚNICAMENTE la información del MANUAL de abajo. No inventes pantallas, botones, montos ni datos.',
            'Si el manual no responde la pregunta, dilo con honestidad y sugiere consultar a Hacienda ('.config('otb.telefono_finanzas').').',
            'No tienes acceso a datos de socios, deudas ni pagos: si preguntan por montos o datos personales, indica dónde verlos en el sistema.',
            'Responde solo sobre el uso del sistema; si preguntan otra cosa, di amablemente que solo ayudas con H2O Control.',
            '', 'MANUAL:', $manual,
        ]);

        $mensajes = [['role' => 'system', 'content' => $sistema]];
        foreach (array_slice($conversacion, -4) as $m) {
            $mensajes[] = ['role' => $m['rol'] === 'asistente' ? 'assistant' : 'user', 'content' => Str::limit($m['texto'], 600)];
        }
        $mensajes[] = ['role' => 'user', 'content' => $pregunta];

        $respuesta = Http::withToken(config('services.groq.key'))
            ->timeout(20)
            ->post(config('services.groq.url'), [
                'model' => config('services.groq.model'),
                'messages' => $mensajes,
                'temperature' => 0.2,
                'max_tokens' => 700,
            ])
            ->throw()
            ->json('choices.0.message.content');

        $texto = trim((string) $respuesta);
        if ($texto === '') {
            throw new RuntimeException('La IA respondió vacío.');
        }

        return $texto;
    }

    /**
     * Palabras significativas, en minúsculas y sin tildes.
     *
     * @return list<string>
     */
    private static function palabras(string $texto): array
    {
        $limpio = Str::lower(Str::ascii($texto));
        preg_match_all('/[a-z0-9]{2,}/', $limpio, $m);

        return array_values(array_unique(array_filter($m[0], fn ($w) => ! in_array($w, self::VACIAS, true))));
    }
}
