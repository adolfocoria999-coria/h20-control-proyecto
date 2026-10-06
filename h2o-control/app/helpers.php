<?php

/**
 * Tu archivo personalizado de Helpers para la OTB
 * Aquí puedes agregar funciones globales más adelante.
 */
if (! function_exists('formatear_moneda')) {
    function formatear_moneda($cantidad)
    {
        return 'Bs. '.number_format($cantidad, 2);
    }
}
