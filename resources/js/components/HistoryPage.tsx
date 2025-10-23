import { useState, useEffect } from 'react';
import { api } from '../services/api';
import type { ImportJobHistoryItem } from '../types/api';

interface HistoryPageProps {
    portalId: number;
    onBack: () => void;
}

export const HistoryPage: React.FC<HistoryPageProps> = ({ portalId, onBack }) => {
    const [jobs, setJobs] = useState<ImportJobHistoryItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        loadHistory();
    }, [portalId]);

    const loadHistory = async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await api.getImportHistory(portalId);
            setJobs(response.data);
        } catch (err) {
            setError('Ошибка загрузки истории импортов');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handleDownloadErrorLog = (jobId: number) => {
        api.downloadErrorLog(jobId);
    };

    const getStatusBadge = (status: string) => {
        const badges: Record<string, string> = {
            pending: 'badge-warning',
            processing: 'badge-info',
            completed: 'badge-success',
            failed: 'badge-error'
        };
        return badges[status] || 'badge-default';
    };

    const getStatusText = (status: string) => {
        const texts: Record<string, string> = {
            pending: 'Ожидание',
            processing: 'В процессе',
            completed: 'Завершен',
            failed: 'Ошибка'
        };
        return texts[status] || status;
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleString('ru-RU', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    if (loading) {
        return (
            <div className="history-page">
                <div className="loading-spinner">
                    <p>Загрузка истории импортов...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="history-page">
            <div className="history-header">
                <h2>История импортов</h2>
                <button onClick={onBack} className="btn btn-secondary">
                    Назад к импорту
                </button>
            </div>

            {error && (
                <div className="alert alert-error">
                    {error}
                    <button onClick={loadHistory} className="btn btn-sm">
                        Повторить
                    </button>
                </div>
            )}

            {jobs.length === 0 ? (
                <div className="empty-state">
                    <p>История импортов пуста</p>
                </div>
            ) : (
                <div className="history-table-container">
                    <table className="history-table">
                        <thead>
                            <tr>
                                <th>Дата создания</th>
                                <th>Имя файла</th>
                                <th>Статус</th>
                                <th>Прогресс</th>
                                <th>Строки</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            {jobs.map((job) => (
                                <tr key={job.job_id}>
                                    <td>{formatDate(job.created_at)}</td>
                                    <td className="filename-cell">
                                        {job.original_filename}
                                    </td>
                                    <td>
                                        <span className={`badge ${getStatusBadge(job.status)}`}>
                                            {getStatusText(job.status)}
                                        </span>
                                    </td>
                                    <td>
                                        <div className="progress-cell">
                                            <div className="progress-bar-small">
                                                <div
                                                    className="progress-bar-fill"
                                                    style={{ width: `${job.progress_percentage}%` }}
                                                />
                                            </div>
                                            <span className="progress-text">
                                                {job.progress_percentage.toFixed(1)}%
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span className="rows-info">
                                            {job.processed_rows} / {job.total_rows}
                                            {job.has_errors && (
                                                <span className="error-count">
                                                    ({job.error_count} ошибок)
                                                </span>
                                            )}
                                        </span>
                                    </td>
                                    <td>
                                        {job.has_errors && (job.status === 'completed' || job.status === 'failed') && (
                                            <button
                                                onClick={() => handleDownloadErrorLog(job.job_id)}
                                                className="btn btn-sm btn-download"
                                            >
                                                📥 Скачать отчет
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <style>{`
                .history-page {
                    padding: 20px;
                    max-width: 1400px;
                    margin: 0 auto;
                }

                .history-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 30px;
                }

                .history-header h2 {
                    margin: 0;
                    font-size: 24px;
                    color: #333;
                }

                .loading-spinner {
                    text-align: center;
                    padding: 60px 20px;
                    color: #666;
                }

                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    color: #999;
                    font-size: 16px;
                }

                .history-table-container {
                    overflow-x: auto;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }

                .history-table {
                    width: 100%;
                    border-collapse: collapse;
                }

                .history-table th,
                .history-table td {
                    padding: 12px 16px;
                    text-align: left;
                    border-bottom: 1px solid #eee;
                }

                .history-table th {
                    background: #f8f9fa;
                    font-weight: 600;
                    color: #555;
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .history-table tbody tr:hover {
                    background: #f9fafb;
                }

                .filename-cell {
                    font-weight: 500;
                    color: #333;
                    max-width: 300px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .badge {
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 600;
                    display: inline-block;
                }

                .badge-warning {
                    background: #fff3cd;
                    color: #856404;
                }

                .badge-info {
                    background: #d1ecf1;
                    color: #0c5460;
                }

                .badge-success {
                    background: #d4edda;
                    color: #155724;
                }

                .badge-error {
                    background: #f8d7da;
                    color: #721c24;
                }

                .progress-cell {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .progress-bar-small {
                    flex: 1;
                    height: 8px;
                    background: #e9ecef;
                    border-radius: 4px;
                    overflow: hidden;
                    min-width: 80px;
                }

                .progress-bar-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #4CAF50 0%, #45a049 100%);
                    transition: width 0.3s ease;
                }

                .progress-text {
                    font-size: 13px;
                    color: #666;
                    white-space: nowrap;
                    min-width: 45px;
                }

                .rows-info {
                    font-size: 14px;
                    color: #555;
                }

                .error-count {
                    color: #dc3545;
                    font-size: 12px;
                    margin-left: 6px;
                    font-weight: 500;
                }

                .btn {
                    padding: 8px 16px;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.2s;
                }

                .btn-secondary {
                    background: #6c757d;
                    color: white;
                }

                .btn-secondary:hover {
                    background: #5a6268;
                }

                .btn-sm {
                    padding: 6px 12px;
                    font-size: 13px;
                }

                .btn-download {
                    background: #007bff;
                    color: white;
                }

                .btn-download:hover {
                    background: #0056b3;
                }

                .alert {
                    padding: 12px 16px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .alert-error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
            `}</style>
        </div>
    );
};

