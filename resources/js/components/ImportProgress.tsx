import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import type { ImportJobStatus } from '../types/api';

interface ImportProgressProps {
    jobId: number;
    onComplete: () => void;
}

export function ImportProgress({ jobId, onComplete }: ImportProgressProps) {
    const [status, setStatus] = useState<ImportJobStatus | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let interval: NodeJS.Timeout | null = null;

        const loadStatus = async () => {
            try {
                const data = await api.getImportStatus(jobId);
                setStatus(data);
                setError(null);

                if (data.status === 'completed' || data.status === 'failed') {
                    if (interval) {
                        clearInterval(interval);
                        interval = null;
                    }
                }
            } catch (err) {
                setError('Ошибка получения статуса импорта');
                console.error(err);
            }
        };

        loadStatus();
        interval = setInterval(loadStatus, 2000);

        return () => {
            if (interval) {
                clearInterval(interval);
            }
        };
    }, [jobId]);

    if (!status) {
        return <div className="loading">Загрузка статуса...</div>;
    }

    const getStatusText = (status: string) => {
        switch (status) {
            case 'pending': return 'Ожидание';
            case 'processing': return 'Обработка';
            case 'completed': return 'Завершено';
            case 'failed': return 'Ошибка';
            default: return status;
        }
    };

    const isFinished = status.status === 'completed' || status.status === 'failed';

    return (
        <div className="import-progress">
            <h2>Прогресс импорта</h2>

            <div className="status-info">
                <p><strong>Файл:</strong> {status.original_filename}</p>
                <p><strong>Статус:</strong> <span className={`status-${status.status}`}>{getStatusText(status.status)}</span></p>
                <p><strong>Обработано:</strong> {status.processed_rows} из {status.total_rows}</p>
            </div>

            <div className="progress-bar">
                <div
                    className="progress-fill"
                    style={{ width: `${status.progress_percentage}%` }}
                >
                    {status.progress_percentage.toFixed(1)}%
                </div>
            </div>

            {status.error_details && status.error_details.length > 0 && (
                <div className="error-details">
                    <h3>Детали ошибок:</h3>
                    <div className="error-list">
                        {status.error_details.map((error, index) => (
                            <div key={index} className="error-item">
                                {error.row && <p><strong>Строка {error.row}:</strong></p>}
                                <p className="error-message">{error.error}</p>
                                {error.data && (
                                    <details className="error-data">
                                        <summary>Дополнительные данные</summary>
                                        <pre>{JSON.stringify(error.data, null, 2)}</pre>
                                    </details>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {error && <p className="error">{error}</p>}

            {isFinished && (
                <div className="actions">
                    <button onClick={onComplete} className="btn btn-primary">
                        Начать новый импорт
                    </button>
                </div>
            )}

            {!isFinished && (
                <p className="info-text">Импорт выполняется в фоновом режиме. Вы можете закрыть эту страницу.</p>
            )}
        </div>
    );
}

