{{-- Plantilla común de los PDF (dompdf: CSS 2.1, sin Tailwind ni emojis) --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('titulo')</title>
    <style>
        @page { margin: 26mm 16mm 18mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #172554; }
        header { position: fixed; top: -19mm; left: 0; right: 0; height: 15mm; border-bottom: 2px solid #1e3a8a; }
        header .otb { font-size: 14px; font-weight: bold; color: #1e3a8a; }
        header .sub { font-size: 9px; color: #0369a1; }
        header .fecha { position: absolute; right: 0; top: 2mm; text-align: right; font-size: 8px; color: #64748b; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; border-top: 1px solid #cbd5e1; font-size: 8px; color: #64748b; }
        footer .pagina:after { content: counter(page); }
        h1 { font-size: 16px; margin: 0 0 2px 0; color: #1e3a8a; }
        .subtitulo { font-size: 10px; color: #0369a1; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        .tabla th { background: #1e3a8a; color: #fff; font-size: 9px; text-transform: uppercase; padding: 6px; text-align: left; }
        .tabla td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
        .tabla th.der { text-align: right; }
        .tabla th.centro { text-align: center; }
        .tabla tr:nth-child(even) td { background: #f0f9ff; }
        .tabla tfoot td { font-weight: bold; background: #e0f2fe; border-top: 2px solid #1e3a8a; }
        .der { text-align: right; }
        .centro { text-align: center; }
        .negrita { font-weight: bold; }
        .tenue { color: #64748b; }
        .caja { border: 1px solid #bae6fd; background: #f0f9ff; padding: 10px; border-radius: 4px; }
        .monto-grande { font-size: 20px; font-weight: bold; color: #047857; }
        .firmas { margin-top: 55px; }
        .firmas td { width: 50%; text-align: center; padding: 0 20px; }
        .linea-firma { border-top: 1px solid #172554; padding-top: 4px; font-size: 9px; }
        .sello { display: inline-block; border: 2px solid #047857; color: #047857; font-weight: bold; padding: 3px 10px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>
    <header>
        <div class="otb">{{ config('otb.nombre') }}</div>
        <div class="sub">Servicio de Agua Potable · H2O Control</div>
        <div class="fecha">Generado el {{ now()->format('d/m/Y H:i') }}<br>por {{ auth()->user()?->name }}</div>
    </header>

    <footer>
        <table><tr>
            <td>{{ config('otb.nombre') }} · Consultas: {{ config('otb.telefono_finanzas') }}</td>
            <td class="der">Página <span class="pagina"></span></td>
        </tr></table>
    </footer>

    @yield('contenido')
</body>
</html>
