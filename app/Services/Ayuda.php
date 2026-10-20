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

        // Las fuentes salen de la pregunta; si es una repregunta corta ("¿y en efectivo?"),
        // se usa también lo último que preguntó el usuario
        $secciones = self::buscar($pregunta, $rol);
        if (! $secciones && $conversacion) {
            $anterior = collect($conversacion)->where('rol', 'usuario')->last()['texto'] ?? '';
            $secciones = self::buscar($pregunta.' '.$anterior, $rol);
        }
        $fuentes = array_column($secciones, 'titulo');

        $charla = self::esCharla($pregunta);
        $consultaTemas = self::preguntaPorTemas($pregunta);

        if (config('services.groq.key')) {
            try {
                return [
                    'respuesta' => self::preguntarIa($pregunta, $rol, $secciones, $conversacion),
                    'fuentes' => $charla || $consultaTemas ? [] : $fuentes, // un saludo no "cita" el manual
                    'modo' => 'ia',
                ];
            } catch (Throwable $e) {
                report($e); // sin IA disponible: se responde con el manual
            }
        }

        if ($consultaTemas) {
            $temas = self::temas($rol);

            return [
                'respuesta' => "Puedo explicarte estos temas del sistema:\n".implode("\n", array_map(fn ($t) => "- {$t}", $temas))
                    ."\n\nPregúntame por cualquiera de ellos.",
                'fuentes' => [],
                'modo' => 'manual',
            ];
        }

        if ($charla) {
            return [
                'respuesta' => '¡Hola! 👋 Soy la ayuda de H2O Control. Pregúntame cómo hacer algo en el sistema, por ejemplo: "¿cómo pago con QR?", "¿dónde veo mi recibo?" o "¿cómo sé si tengo una fuga?".',
                'fuentes' => [],
                'modo' => 'manual',
            ];
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

        $temas = implode("\n", array_map(fn ($t) => "- {$t}", self::temas($rol)));

        $sistema = implode("\n", [
            'Eres el asistente de ayuda de "H2O Control", el sistema de agua potable de la '.config('otb.nombre').' (Bolivia).',
            "Quien pregunta tiene el rol: {$rolNombre}.",
            'Responde en español, breve y claro, con pasos numerados cuando sea un procedimiento. Usa **negrita** para nombres de botones y menús.',
            'Usa ÚNICAMENTE la información del MANUAL de abajo. No inventes pantallas, botones, montos ni datos.',
            'Si el manual no responde una pregunta sobre el sistema, dilo con honestidad y sugiere consultar a Hacienda ('.config('otb.telefono_finanzas').').',
            'Saludos o agradecimientos: responde cordial y breve, y ofrece 2 o 3 ejemplos tomados de los TEMAS.',
            'Si preguntan qué puedes hacer, qué puedes enseñar o qué hace el sistema: resume los TEMAS de abajo agrupados por área (máximo 8 puntos). Es la lista completa de lo que el sistema hace para este rol.',
            'NUNCA menciones funciones, alertas, configuraciones ni pantallas que no estén en los TEMAS o en el MANUAL.',
            'Preguntas básicas sobre el servicio de agua (qué es un m³, cómo leer el medidor, cómo detectar una fuga, cómo ahorrar agua, qué es la OTB): responde en 2 a 4 frases con conocimiento general, sin inventar reglas, montos ni funciones del sistema.',
            'Si las secciones del MANUAL no tienen relación con la pregunta, ignóralas.',
            'No tienes acceso a datos de socios, deudas ni pagos: si preguntan por montos o datos personales, indica dónde verlos en el sistema.',
            'Si preguntan algo sin relación con el sistema ni con el servicio de agua (deportes, tareas, política, etc.), di amablemente que solo ayudas con H2O Control y el servicio de agua.',
            '', 'TEMAS QUE PUEDES EXPLICAR A ESTE ROL:', $temas,
            '', 'MANUAL (secciones relacionadas con la pregunta):', $manual,
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
     * Títulos de todo lo que el manual explica a este rol.
     *
     * @return list<string>
     */
    public static function temas(?RolUsuario $rol): array
    {
        return array_column(self::manual($rol), 'titulo');
    }

    // "¿qué puedes hacer / enseñarme?", "¿qué hace el sistema?", "¿en qué me ayudas?"
    private static function preguntaPorTemas(string $pregunta): bool
    {
        $texto = Str::lower(Str::ascii($pregunta));

        return (bool) preg_match('/(que|cuales).{0,25}(puedes|sabes|podes) (hacer|ensenar|explicar)|(puedes|podes) (ensenar|explicar)me|que (hace|puede hacer|funciones tiene) (el sistema|la app|h2o)|en que me (ayudas|puedes ayudar)|que temas/', $texto);
    }

    // "hola", "buenas tardes", "gracias": no es una consulta del manual
    private static function esCharla(string $pregunta): bool
    {
        $texto = trim(preg_replace('/[^a-z ]/', '', Str::lower(Str::ascii($pregunta))));

        return (bool) preg_match('/^(hola|buenas|buenos dias|buenas tardes|buenas noches|gracias|muchas gracias|ok|okay|vale|chau|adios|hasta luego|quien eres|ayuda)( .{0,20})?$/', $texto);
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
