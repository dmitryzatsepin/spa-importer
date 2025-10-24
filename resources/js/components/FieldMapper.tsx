import { useState } from 'react';
import type { SmartProcessField, FieldMapping } from '../types/api';

interface FieldMapperProps {
    fileColumns: string[];
    processFields: SmartProcessField[];
    onSubmit: (mappings: FieldMapping[]) => void;
    onBack: () => void;
}

export function FieldMapper({ fileColumns, processFields, onSubmit, onBack }: FieldMapperProps) {
    const [mappings, setMappings] = useState<Record<string, string>>({});

    const handleMappingChange = (targetField: string, sourceColumn: string) => {
        setMappings(prev => ({
            ...prev,
            [targetField]: sourceColumn
        }));
    };

    const handleSubmit = () => {
        const fieldMappings: FieldMapping[] = Object.entries(mappings)
            .filter(([_, source]) => source !== '')
            .map(([target, source]) => ({ target, source }));

        if (fieldMappings.length === 0) {
            alert('Необходимо настроить хотя бы одно сопоставление полей');
            return;
        }

        const requiredFields = processFields.filter(f => f.isRequired);
        const mappedTargets = new Set(fieldMappings.map(m => m.target));
        const missingRequired = requiredFields.filter(f => !mappedTargets.has(f.code));

        if (missingRequired.length > 0) {
            const missing = missingRequired.map(f => f.title).join(', ');
            if (!confirm(`Не заполнены обязательные поля: ${missing}. Продолжить?`)) {
                return;
            }
        }

        onSubmit(fieldMappings);
    };

    return (
        <div className="field-mapper">
            <h2>Сопоставление полей</h2>

            <div className="mapping-table">
                <div className="table-header">
                    <div className="col">Поле в Битрикс24</div>
                    <div className="col">Колонка в файле</div>
                </div>

                {processFields.filter(f => !f.isReadOnly).map((field) => (
                    <div key={field.code} className="mapping-row">
                        <div className="field-info">
                            <span className="field-title">
                                {field.title}
                                {field.isRequired && <span className="required">*</span>}
                            </span>
                            <span className="field-code">{field.code}</span>
                            <span className="field-type">{field.type}</span>
                        </div>

                        <div className="select-wrapper">
                            <select
                                value={mappings[field.code] || ''}
                                onChange={(e) => handleMappingChange(field.code, e.target.value)}
                                className="form-select"
                            >
                                <option value="">-- Не импортировать --</option>
                                {fileColumns.map((column) => (
                                    <option key={column} value={column}>
                                        {column}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                ))}
            </div>

            <div className="actions">
                <button onClick={onBack} className="btn btn-secondary">
                    Назад
                </button>
                <button onClick={handleSubmit} className="btn btn-primary">
                    Начать импорт
                </button>
            </div>
        </div>
    );
}

