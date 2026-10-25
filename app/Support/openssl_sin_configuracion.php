<?php

/*
 * Notificaciones en el celular sin openssl.cnf en el servidor.
 *
 * Para cifrar cada notificación, las librerías de Web Push crean y exportan
 * claves con openssl_pkey_new() y openssl_pkey_export(), que fallan si el
 * servidor no tiene un openssl.cnf (pasa en Wasmer y en PHP de Windows sin
 * OPENSSL_CONF). Estas funciones, en los mismos namespaces que esas
 * librerías, las reemplazan: intentan normal y, si falla, repiten con la
 * configuración mínima incluida en el proyecto.
 */

namespace App\Support {
    final class OpensslSinConfiguracion
    {
        public static function archivo(): string
        {
            return \dirname(__DIR__, 2).'/resources/openssl/openssl.cnf';
        }

        public static function nuevaClave(?array $opciones): \OpenSSLAsymmetricKey|false
        {
            $clave = @\openssl_pkey_new($opciones);

            if ($clave === false && ! isset($opciones['config'])) {
                self::limpiarErrores();
                $clave = \openssl_pkey_new(($opciones ?? []) + ['config' => self::archivo()]);
            }

            return $clave;
        }

        public static function exportar($clave, &$salida, ?string $frase, ?array $opciones): bool
        {
            if (@\openssl_pkey_export($clave, $salida, $frase, $opciones)) {
                return true;
            }
            if (isset($opciones['config'])) {
                return false;
            }
            self::limpiarErrores();

            return \openssl_pkey_export($clave, $salida, $frase, ($opciones ?? []) + ['config' => self::archivo()]);
        }

        private static function limpiarErrores(): void
        {
            while (\openssl_error_string() !== false) {
                // Descarta los errores del primer intento
            }
        }
    }
}

namespace Minishlink\WebPush {
    function openssl_pkey_new(?array $opciones = null): \OpenSSLAsymmetricKey|false
    {
        return \App\Support\OpensslSinConfiguracion::nuevaClave($opciones);
    }
}

namespace Jose\Component\Core\Util {
    function openssl_pkey_new(?array $opciones = null): \OpenSSLAsymmetricKey|false
    {
        return \App\Support\OpensslSinConfiguracion::nuevaClave($opciones);
    }

    function openssl_pkey_export($clave, &$salida, ?string $frase = null, ?array $opciones = null): bool
    {
        return \App\Support\OpensslSinConfiguracion::exportar($clave, $salida, $frase, $opciones);
    }
}

namespace Jose\Component\KeyManagement {
    function openssl_pkey_new(?array $opciones = null): \OpenSSLAsymmetricKey|false
    {
        return \App\Support\OpensslSinConfiguracion::nuevaClave($opciones);
    }

    function openssl_pkey_export($clave, &$salida, ?string $frase = null, ?array $opciones = null): bool
    {
        return \App\Support\OpensslSinConfiguracion::exportar($clave, $salida, $frase, $opciones);
    }
}

namespace Jose\Component\KeyManagement\KeyConverter {
    function openssl_pkey_export($clave, &$salida, ?string $frase = null, ?array $opciones = null): bool
    {
        return \App\Support\OpensslSinConfiguracion::exportar($clave, $salida, $frase, $opciones);
    }
}
