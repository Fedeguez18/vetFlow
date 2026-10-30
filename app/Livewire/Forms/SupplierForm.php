<?php

namespace App\Livewire\Forms;

use App\Models\Producer;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Supplier create/edit form.
 */
class SupplierForm extends Form
{
    /**
     * Text fields mapped one to one to the supplier columns.
     */
    protected const FIELDS = ['business_name', 'contact_name', 'cuit', 'iva_condition', 'phone', 'email', 'address', 'city', 'payment_terms', 'notes'];

    public ?Supplier $supplier = null;

    public string $business_name = '';

    public string $contact_name = '';

    public string $cuit = '';

    public string $iva_condition = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $city = '';

    public string $payment_terms = '';

    public string $notes = '';

    public function setSupplier(Supplier $supplier): void
    {
        $this->supplier = $supplier;

        foreach (self::FIELDS as $field) {
            $this->{$field} = (string) $supplier->{$field};
        }
    }

    public function isEditing(): bool
    {
        return $this->supplier !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'cuit' => ['nullable', 'regex:/^\d{2}-\d{8}-\d$/', Rule::unique('suppliers', 'cuit')->ignore($this->supplier?->id)],
            'iva_condition' => ['nullable', Rule::in(Producer::IVA_CONDITIONS)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_name.required' => 'Ingresá la razón social.',
            'cuit.regex' => 'El CUIT debe tener 11 dígitos (ej.: 30-71234567-1).',
            'cuit.unique' => 'Ya existe un proveedor con ese CUIT.',
            'iva_condition.in' => 'Elegí una condición de IVA válida.',
            'email.email' => 'El email no es válido.',
            '*.max' => 'No puede superar los :max caracteres.',
        ];
    }

    /**
     * Trim the inputs, lowercase the email and turn "30712345671" into "30-71234567-1" before validating.
     */
    public function normalize(SupplierService $suppliers): void
    {
        foreach (self::FIELDS as $field) {
            $this->{$field} = trim($this->{$field});
        }

        $this->email = Str::lower($this->email);
        $this->cuit = $suppliers->normalizeCuit($this->cuit);
    }

    /**
     * Validated supplier attributes; empty fields are stored as null.
     *
     * @return array<string, ?string>
     */
    public function attributes(): array
    {
        return collect(self::FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $this->{$field} !== '' ? $this->{$field} : null])
            ->all();
    }
}
