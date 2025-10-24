import { useState } from 'react';
import { SmartProcessSelector } from './components/SmartProcessSelector';
import { FileUploader } from './components/FileUploader';
import { FieldMapper } from './components/FieldMapper';
import { ImportProgress } from './components/ImportProgress';
import { HistoryPage } from './components/HistoryPage';
import type { SmartProcess, SmartProcessField, FieldMapping, AppConfig } from './types/api';
import { api } from './services/api';

interface AppProps {
    config: AppConfig;
}

type Step = 'select-process' | 'upload-file' | 'map-fields' | 'import-progress';
type View = 'import' | 'history';

export default function App({ config }: AppProps) {
    const [currentView, setCurrentView] = useState<View>('import');
    const [currentStep, setCurrentStep] = useState<Step>('select-process');
    const [selectedProcess, setSelectedProcess] = useState<SmartProcess | null>(null);
    const [processFields, setProcessFields] = useState<SmartProcessField[]>([]);
    const [uploadedFile, setUploadedFile] = useState<File | null>(null);
    const [fileColumns, setFileColumns] = useState<string[]>([]);
    const [jobId, setJobId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const portalId = config.portal_id;

    const handleProcessSelect = async (process: SmartProcess) => {
        try {
            setError(null);
            setSelectedProcess(process);
            const fields = await api.getSmartProcessFields(process.id, portalId as number);
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
            const result = await api.startImport(uploadedFile, portalId as number, selectedProcess.id, mappings);
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
        setJobId(null);
        setError(null);
    };

    return (
        <div className="import-app">
            <header className="app-header">
                <div className="header-content">
                    <div className="header-title">
                        <h1>Импорт данных в Битрикс24</h1>
                        {config.domain && <p className="portal-info">Портал: {config.domain}</p>}
                    </div>
                    <nav className="header-nav">
                        <button
                            className={`nav-btn ${currentView === 'import' ? 'active' : ''}`}
                            onClick={() => setCurrentView('import')}
                        >
                            Новый импорт
                        </button>
                        <button
                            className={`nav-btn ${currentView === 'history' ? 'active' : ''}`}
                            onClick={() => setCurrentView('history')}
                        >
                            История
                        </button>
                    </nav>
                </div>
            </header>

            {error && (
                <div className="alert alert-error">
                    {error}
                </div>
            )}

            <div className="app-content">
                {!portalId && (
                    <div className="alert alert-error">
                        Не указан portal_id. Обновите страницу установки или авторизуйтесь заново.
                    </div>
                )}
                {currentView === 'history' ? (
                    <HistoryPage
                        portalId={portalId as number}
                        onBack={() => setCurrentView('import')}
                    />
                ) : (
                    <>
                        {currentStep === 'select-process' && portalId && (
                            <SmartProcessSelector
                                portalId={portalId}
                                onSelect={handleProcessSelect}
                            />
                        )}

                        {currentStep === 'upload-file' && selectedProcess && portalId && (
                            <FileUploader
                                onUpload={handleFileUpload}
                                onBack={() => setCurrentStep('select-process')}
                            />
                        )}

                        {currentStep === 'map-fields' && portalId && (
                            <FieldMapper
                                fileColumns={fileColumns}
                                processFields={processFields}
                                onSubmit={handleFieldMappingsSubmit}
                                onBack={() => setCurrentStep('upload-file')}
                            />
                        )}

                        {currentStep === 'import-progress' && jobId && portalId && (
                            <ImportProgress
                                jobId={jobId}
                                onComplete={handleReset}
                            />
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

