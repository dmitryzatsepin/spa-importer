<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
            'portal_id' => ['required', 'integer', 'exists:portals,id'],
            'entity_type_id' => ['required', 'integer'],
            'field_mappings' => ['required', 'array'],
            'field_mappings.*.source' => ['required', 'string'],
            'field_mappings.*.target' => ['required', 'string'],
            'settings' => ['nullable', 'array'],
            'settings.duplicate_handling' => ['nullable', 'string', 'in:skip,update,create_new'],
            'settings.duplicate_field' => ['nullable', 'string'],
            'settings.batch_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Необходимо загрузить файл для импорта',
            'file.mimes' => 'Файл должен быть в формате CSV, XLSX или XLS',
            'file.max' => 'Размер файла не должен превышать 10 МБ',
            'portal_id.required' => 'Необходимо указать ID портала',
            'portal_id.exists' => 'Указанный портал не найден',
            'entity_type_id.required' => 'Необходимо указать ID смарт-процесса',
            'field_mappings.required' => 'Необходимо указать сопоставление полей',
            'field_mappings.array' => 'Сопоставление полей должно быть массивом',
            'settings.duplicate_handling.in' => 'Недопустимое значение для обработки дубликатов',
        ];
    }
}

