<?php

namespace App\Http\Requests;

use App\Livewire\Pos\PosDesk;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A sale the POS made without internet, as the browser uploads it.
 */
class SyncOfflineSaleRequest extends FormRequest
{
    /**
     * The terminal middleware already checked the PC is an enabled terminal.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'sold_at' => ['required', 'date'],
            'cash_session_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(['contado', 'remito'])],
            'payment_method' => ['required', Rule::in(array_keys(PosDesk::PAYMENT_METHODS))],
            'producer_id' => ['nullable', 'required_if:type,remito', 'integer', 'exists:producers,id'],
            'voucher_number' => ['nullable', 'string', 'max:30'],
            'provisional_number' => ['nullable', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
