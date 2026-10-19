<?php

namespace App\Livewire\Forms;

use App\Models\Producer;
use App\Support\Pricing;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * New current account (estancia / productor) form.
 */
class ProducerForm extends Form
{
    public string $name = '';

    public string $business_name = '';

    public string $cuit = '';

    public string $phone = '';

    public string $iva_condition = '';

    public string $discount_percent = '0';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('producers', 'name')],
            'business_name' => ['nullable', 'string', 'max:255'],
            'cuit' => ['nullable', 'regex:/^\d{2}-\d{8}-\d$/', Rule::unique('producers', 'cuit')],
            'phone' => ['nullable', 'string', 'max:30'],
            'iva_condition' => ['nullable', Rule::in(Producer::IVA_CONDITIONS)],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.unique' => 'Ya existe una cuenta con ese nombre.',
            'cuit.regex' => 'El CUIT tiene que tener 11 dígitos.',
            'cuit.unique' => 'Ya existe una cuenta con ese CUIT.',
            'phone.max' => 'El teléfono no puede superar los 30 caracteres.',
            'iva_condition.in' => 'Elegí una condición de IVA válida.',
            'discount_percent.required' => 'Obligatorio.',
            'discount_percent.numeric' => 'Tiene que ser un número.',
            'discount_percent.min' => 'No puede ser negativo.',
            'discount_percent.max' => 'No puede superar el 100%.',
        ];
    }

    /**
     * Trim the inputs and turn "20284890294" into "20-28489029-4" before validating.
     */
    public function normalize(): void
    {
        $this->name = trim($this->name);
        $this->business_name = trim($this->business_name);
        $this->phone = trim($this->phone);

        $digits = preg_replace('/\D/', '', $this->cuit);
        $this->cuit = strlen($digits) === 11
            ? substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10)
            : trim($this->cuit);

        $discount = Pricing::parse($this->discount_percent);
        $this->discount_percent = $discount === null ? trim($this->discount_percent) : (string) $discount;
    }

    /**
     * Validated producer attributes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'business_name' => $this->business_name ?: null,
            'cuit' => $this->cuit ?: null,
            'phone' => $this->phone ?: null,
            'iva_condition' => $this->iva_condition ?: null,
            'discount_percent' => round((float) $this->discount_percent, 2),
        ];
    }
}
