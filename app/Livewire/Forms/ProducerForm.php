<?php

namespace App\Livewire\Forms;

use App\Models\Producer;
use App\Support\Pricing;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Current account (estancia / productor) create/edit form.
 */
class ProducerForm extends Form
{
    public ?Producer $producer = null;

    public string $name = '';

    public string $business_name = '';

    public string $cuit = '';

    public string $phone = '';

    public string $iva_condition = '';

    public string $discount_percent = '0';

    /**
     * Empty means no credit limit.
     */
    public string $credit_limit = '';

    public function setProducer(Producer $producer): void
    {
        $this->producer = $producer;
        $this->name = $producer->name;
        $this->business_name = (string) $producer->business_name;
        $this->cuit = (string) $producer->cuit;
        $this->phone = (string) $producer->phone;
        $this->iva_condition = (string) $producer->iva_condition;
        $this->discount_percent = ProductForm::plain($producer->discount_percent);
        $this->credit_limit = $producer->credit_limit === null ? '' : ProductForm::plain($producer->credit_limit);
    }

    public function isEditing(): bool
    {
        return $this->producer !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('producers', 'name')->ignore($this->producer?->id)],
            'business_name' => ['nullable', 'string', 'max:255'],
            'cuit' => ['nullable', 'regex:/^\d{2}-\d{8}-\d$/', Rule::unique('producers', 'cuit')->ignore($this->producer?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'iva_condition' => ['nullable', Rule::in(Producer::IVA_CONDITIONS)],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
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
            'discount_percent.max' => 'No puede superar el 100%.',
            '*.numeric' => 'Tiene que ser un número.',
            '*.min' => 'No puede ser negativo.',
        ];
    }

    /**
     * Trim the inputs, turn "20284890294" into "20-28489029-4" and the typed amounts
     * ("1.500.000") into plain numbers before validating.
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

        foreach (['discount_percent', 'credit_limit'] as $field) {
            $value = Pricing::parse($this->{$field});
            $this->{$field} = $value === null ? trim($this->{$field}) : ProductForm::plain($value);
        }
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
            'credit_limit' => $this->credit_limit === '' ? null : round((float) $this->credit_limit, 2),
        ];
    }
}
