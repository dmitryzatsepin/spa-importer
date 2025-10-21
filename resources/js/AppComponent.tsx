import React, { useState, useEffect } from 'react';
import { SmartProcessSelector } from './components/SmartProcessSelector';
import { FileUploader } from './components/FileUploader';
import { FieldMapper } from './components/FieldMapper';
import { ImportProgress } from './components/ImportProgress';
import type { SmartProcess, SmartProcessField, FieldMapping, AppConfig } from './types/api';
import { api } from './services/api';

interface AppProps {
    config: AppConfig;
}

type Step = 'select-process' | 'upload-file' | 'map-fields' | 'import-progress';

export default function App({ config }: AppProps) {
    const [currentStep, setCurrentStep] = useState<Step>('select-process');
    const [selectedProcess, setSelectedProcess] = useState<SmartProcess | null>(null);
    const [processFields, setProcessFields] = useState<SmartProcessField[]>([]);
    const [uploadedFile, setUploadedFile] = useState<File | null>(null);
    const [fileColumns, setFileColumns] = useState<string[]>([]);
    const [fieldMappings, setFieldMappings] = useState<FieldMapping[]>([]);
    const [jobId, setJobId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const portalId = config.portal_id || 1;

    const handleProcessSelect = async (process: SmartProcess) => {
        try {
            setError(null);
            setSelectedProcess(process);
            const fields = await api.getSmartProcessFields(process.id, portalId);
            setProcessFields(fields);
            setCurrentStep('upload-file');
        } catch (err) {
            setError('Ошибка загрузки полей смарт-процесса');
            console.error(err);
        }
    };

    const handleFileUpload = (file: File, columns: string[]) => {
        setUploadedFile(file);
        setFileColumns(columns);
        setCurrentStep('map-fields');
    };

    const handleFieldMappingsSubmit = async (mappings: FieldMapping[]) => {
        if (!uploadedFile || !selectedProcess) return;

        try {
            setError(null);
            setFieldMappings(mappings);
            const result = await api.startImport(uploadedFile, portalId, selectedProcess.id, mappings);
            setJobId(result.job_id);
            setCurrentStep('import-progress');
        } catch (err) {
            setError('Ошибка запуска импорта');
            console.error(err);
        }
    };

    const handleReset = () => {
        setCurrentStep('select-process');
        setSelectedProcess(null);
        setProcessFields([]);
        setUploadedFile(null);
        setFileColumns([]);
        setFieldMappings([]);
        setJobId(null);
        setError(null);
    };

    return (
        <div className="import-app">
            <header className="app-header">
                <h1>Импорт данных в Битрикс24</h1>
                {config.domain && <p className="portal-info">Портал: {config.domain}</p>}
            </header>

            {error && (
                <div className="alert alert-error">
                    {error}
                </div>
            )}

            <div className="app-content">
                {currentStep === 'select-process' && (
                    <SmartProcessSelector
                        portalId={portalId}
                        onSelect={handleProcessSelect}
                    />
                )}

                {currentStep === 'upload-file' && selectedProcess && (
                    <FileUploader
                        onUpload={handleFileUpload}
                        onBack={() => setCurrentStep('select-process')}
                    />
                )}

                {currentStep === 'map-fields' && (
                    <FieldMapper
                        fileColumns={fileColumns}
                        processFields={processFields}
                        onSubmit={handleFieldMappingsSubmit}
                        onBack={() => setCurrentStep('upload-file')}
                    />
                )}

                {currentStep === 'import-progress' && jobId && (
                    <ImportProgress
                        jobId={jobId}
                        onComplete={handleReset}
                    />
                )}
            </div>
        </div>
    );
}

