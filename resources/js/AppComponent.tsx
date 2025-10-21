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
                {currentView === 'history' ? (
                    <HistoryPage
                        portalId={portalId}
                        onBack={() => setCurrentView('import')}
                    />
                ) : (
                    <>
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
                    </>
                )}
            </div>

            <style>{`
                .app-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 20px 30px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }

                .header-content {
                    max-width: 1400px;
                    margin: 0 auto;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 20px;
                }

                .header-title h1 {
                    margin: 0 0 5px 0;
                    font-size: 28px;
                    font-weight: 600;
                }

                .portal-info {
                    margin: 0;
                    opacity: 0.9;
                    font-size: 14px;
                }

                .header-nav {
                    display: flex;
                    gap: 10px;
                }

                .nav-btn {
                    padding: 10px 20px;
                    border: 2px solid rgba(255,255,255,0.3);
                    background: rgba(255,255,255,0.1);
                    color: white;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 15px;
                    font-weight: 500;
                    transition: all 0.3s;
                }

                .nav-btn:hover {
                    background: rgba(255,255,255,0.2);
                    border-color: rgba(255,255,255,0.5);
                }

                .nav-btn.active {
                    background: white;
                    color: #667eea;
                    border-color: white;
                }

                .alert {
                    max-width: 1400px;
                    margin: 20px auto;
                    padding: 12px 20px;
                    border-radius: 6px;
                }

                .alert-error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
            `}</style>
        </div>
    );
}

