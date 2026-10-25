<?php

namespace Tests\Feature;

use App\Support\OpensslSinConfiguracion;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

/**
 * Las notificaciones al celular deben funcionar aunque el servidor no tenga
 * openssl.cnf (Wasmer): las claves se crean con la configuración del proyecto.
 */
class OpensslSinConfiguracionTest extends TestCase
{
    public function test_el_proyecto_incluye_la_configuracion_minima_de_openssl(): void
    {
        $this->assertFileExists(OpensslSinConfiguracion::archivo());

        $clave = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC,
            'config' => OpensslSinConfiguracion::archivo()]);
        $this->assertNotFalse($clave);
        $this->assertTrue(openssl_pkey_export($clave, $pem, null, ['config' => OpensslSinConfiguracion::archivo()]));
        $this->assertStringContainsString('PRIVATE KEY', $pem);
    }

    public function test_las_librerias_de_web_push_usan_la_version_con_respaldo(): void
    {
        foreach (['Minishlink\WebPush', 'Jose\Component\Core\Util', 'Jose\Component\KeyManagement'] as $namespace) {
            $this->assertTrue(function_exists($namespace.'\openssl_pkey_new'), $namespace);
        }
        foreach (['Jose\Component\Core\Util', 'Jose\Component\KeyManagement', 'Jose\Component\KeyManagement\KeyConverter'] as $namespace) {
            $this->assertTrue(function_exists($namespace.'\openssl_pkey_export'), $namespace);
        }

        $claves = VAPID::createVapidKeys();
        $this->assertSame(87, strlen($claves['publicKey']));
        $this->assertSame(43, strlen($claves['privateKey']));
    }

    public function test_si_se_indica_una_configuracion_no_la_reemplaza(): void
    {
        $this->assertFalse(OpensslSinConfiguracion::nuevaClave([
            'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'config' => 'no-existe.cnf',
        ]));
        while (openssl_error_string() !== false) {
        }
    }
}
