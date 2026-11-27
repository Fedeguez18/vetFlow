@use('App\Support\Ean13')
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas · veteFlow</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e2e8f0; color: #000; font-family: Arial, Helvetica, sans-serif; }

        .toolbar { position: sticky; top: 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 20px; background: #0F2A26; color: #fff; font-size: 14px; }
        .toolbar button { border: 0; border-radius: 10px; padding: 8px 16px; background: #10b981; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }

        .sheet { width: 210mm; height: 297mm; margin: 10mm auto; padding: 10mm 6mm; background: #fff; display: grid; grid-template-columns: repeat(3, 66mm); grid-template-rows: repeat(8, 34mm); align-content: start; page-break-after: always; break-after: page; }
        .sheet:last-child { page-break-after: auto; break-after: auto; }

        .label { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1mm; padding: 2mm 3mm; border: 0.2mm dashed #cbd5e1; overflow: hidden; }
        .name { width: 100%; text-align: center; font-size: 8pt; font-weight: 700; line-height: 1.15; max-height: 2.3em; overflow: hidden; }
        .bars { width: 50mm; height: 15mm; }
        .bars svg { display: block; width: 100%; height: 100%; }
        .digits { font-family: 'Courier New', monospace; font-size: 9pt; letter-spacing: 0.6mm; }
        .invalid { font-size: 7pt; color: #b91c1c; }

        @media print {
            html, body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>Imprimí en A4 con escala 100% (sin «Ajustar a la página») y recortá por las líneas punteadas.</span>
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    @forelse ($pages as $page)
        <div class="sheet">
            @foreach ($page as $product)
                <div class="label">
                    <div class="name">{{ $product->name }}</div>
                    @if (Ean13::isValid($product->barcode))
                        <div class="bars">{!! Ean13::svg($product->barcode) !!}</div>
                    @else
                        <div class="invalid">Código sin formato EAN-13</div>
                    @endif
                    <div class="digits">{{ $product->barcode }}</div>
                </div>
            @endforeach
        </div>
    @empty
        <p style="padding: 40px; text-align: center;">No hay etiquetas pendientes.</p>
    @endforelse

    @if ($pages->isNotEmpty())
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
