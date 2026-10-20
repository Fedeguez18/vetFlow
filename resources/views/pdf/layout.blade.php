{{-- Shared A4 layout of the printable documents, following the veterinary's paper remito. Rendered by dompdf (CSS 2.1, tables only). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #000; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .sheet { border: 1px solid #000; padding: 10px 12px 14px; }
        .brand { font-family: 'DejaVu Serif', serif; font-weight: bold; font-style: italic; font-size: 10px; }
        .title { font-family: 'DejaVu Serif', serif; font-weight: bold; font-style: italic; font-size: 10px; text-align: right; }
        .label { text-decoration: underline; font-weight: bold; }
        .date-box { border: 1px solid #000; text-align: center; padding: 3px 6px; }
        .grid th { border: 1px solid #000; border-bottom-width: 2px; padding: 3px 4px; font-size: 8px; text-transform: uppercase; }
        .grid td { border: 1px solid #000; padding: 2px 4px; height: 12px; }
        .c { text-align: center; }
        .r { text-align: right; }
        .num { text-align: right; white-space: nowrap; }
        .section { margin-top: 14px; font-weight: bold; text-transform: uppercase; font-size: 8px; }
        .totals { margin-top: 18px; }
        .totals td { padding: 3px 4px; font-weight: bold; text-transform: uppercase; font-size: 8px; }
        .totals .line td { border-bottom: 1px solid #000; }
        .totals .grand td { border-top: 2px solid #000; border-bottom: 2px solid #000; }
        .totals .highlight { background: #eef1f0; }
        .muted { color: #444; }
        .footer { margin-top: 10px; font-size: 7px; text-align: center; color: #444; }
    </style>
</head>
<body>
    <div class="sheet">
        <table>
            <tr>
                <td style="width: 62%;">
                    <div class="brand">{{ config('business.name') }}</div>
                    <div class="brand" style="padding-left: 24px; margin-top: 4px;">{{ config('business.location') }}</div>
                </td>
                <td class="title" style="vertical-align: bottom;">@yield('title')</td>
            </tr>
        </table>

        <table style="margin-top: 14px;">
            <tr>
                <td style="width: 22%;"><span class="label">CLIENTE:</span></td>
                <td style="width: 40%; font-weight: bold;">{{ $client->business_name ? $client->name.' — '.$client->business_name : $client->name }}</td>
                <td class="date-box">@yield('date')</td>
            </tr>
            <tr>
                <td style="padding-top: 3px; padding-left: 12px;">{{ $client->id }}</td>
                <td style="padding-top: 3px;">{{ $client->cuit ?? 'Sin CUIT' }}{{ $client->iva_condition ? ' · '.$client->iva_condition : '' }}</td>
                <td class="c muted" style="padding-top: 3px;">@yield('subtitle')</td>
            </tr>
        </table>

        @yield('content')
    </div>

    <div class="footer">Documento no válido como factura · Generado el {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
