<?php

namespace App\Http\Controllers;

use App\Models\BarcodeLabel;
use Illuminate\Contracts\View\View;

class BarcodeLabelPrintController extends Controller
{
    /**
     * The pending label sheet laid out on A4 pages, ready for the browser's print dialog.
     */
    public function __invoke(): View
    {
        $labels = BarcodeLabel::with('product:id,name,barcode')
            ->oldest()
            ->oldest('id')
            ->get()
            ->flatMap(fn (BarcodeLabel $label) => array_fill(0, $label->copies, $label->product));

        return view('labels.print', [
            'pages' => $labels->chunk(BarcodeLabel::PER_SHEET),
        ]);
    }
}
