import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import type { SmartProcess } from '../types/api';

interface SmartProcessSelectorProps {
    portalId: number;
    onSelect: (process: SmartProcess) => void;
}

export function SmartProcessSelector({ portalId, onSelect }: SmartProcessSelectorProps) {
    const [processes, setProcesses] = useState<SmartProcess[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        loadProcesses();
    }, [portalId]);

    const loadProcesses = async () => {
        try {
            setLoading(true);
            setError(null);
            const data = await api.getSmartProcesses(portalId);
            setProcesses(data);
        } catch (err) {
            setError('Не удалось загрузить список смарт-процессов');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return <div className="loading">Загрузка смарт-процессов...</div>;
    }

    if (error) {
        return (
            <div className="error-container">
                <p className="error">{error}</p>
                <button onClick={loadProcesses} className="btn btn-secondary">
                    Повторить
                </button>
            </div>
        );
    }

    return (
        <div className="smart-process-selector">
            <h2>Выберите смарт-процесс</h2>
            <div className="process-list">
                {processes.length === 0 ? (
                    <p className="no-data">Смарт-процессы не найдены</p>
                ) : (
                    processes.map((process) => (
                        <div
                            key={process.id}
                            className="process-card"
                            onClick={() => onSelect(process)}
                        >
                            <h3>{process.title}</h3>
                            {process.code && <p className="process-code">{process.code}</p>}
                            <span className="process-id">ID: {process.id}</span>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

