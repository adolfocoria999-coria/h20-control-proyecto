<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El archivo subido no es un respaldo de H2O Control, está dañado o es de una
 * versión más nueva del sistema. Su mensaje se puede mostrar al usuario.
 */
class RespaldoInvalido extends RuntimeException {}
