import { useState, useRef } from 'react';

interface FileUploaderProps {
    onUpload: (file: File, columns: string[]) => void;
    onBack: () => void;
}

export function FileUploader({ onUpload, onBack }: FileUploaderProps) {
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const validTypes = [
            'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        if (!validTypes.includes(file.type) && !file.name.match(/\.(csv|xlsx|xls)$/i)) {
            setError('Пожалуйста, выберите файл формата CSV, XLS или XLSX');
            return;
        }

        const maxSize = 10 * 1024 * 1024; // 10 MB
        if (file.size > maxSize) {
            setError('Размер файла не должен превышать 10 МБ');
            return;
        }

        setError(null);
        setSelectedFile(file);
    };

    const handleUpload = async () => {
        if (!selectedFile) return;

        try {
            setError(null);
            const reader = new FileReader();

            reader.onload = (e) => {
                const text = e.target?.result as string;
                const lines = text.split('\n');
                if (lines.length > 0) {
                    const columns = lines[0].split(/[,;\t]/).map(col => col.trim().replace(/^"|"$/g, ''));
                    onUpload(selectedFile, columns);
                }
            };

            if (selectedFile.name.endsWith('.csv')) {
                reader.readAsText(selectedFile);
            } else {
                // Для Excel файлов просто передаем пустой массив колонок
                // В реальном приложении нужно использовать библиотеку типа xlsx
                onUpload(selectedFile, []);
            }
        } catch (err) {
            setError('Ошибка чтения файла');
            console.error(err);
        }
    };

    return (
        <div className="file-uploader">
            <h2>Загрузка файла</h2>

            <div className="upload-area">
                <input
                    ref={fileInputRef}
                    type="file"
                    accept=".csv,.xlsx,.xls"
                    onChange={handleFileSelect}
                    style={{ display: 'none' }}
                />

                <button
                    onClick={() => fileInputRef.current?.click()}
                    className="btn btn-primary"
                >
                    Выбрать файл
                </button>

                {selectedFile && (
                    <div className="file-info">
                        <p><strong>Выбран файл:</strong> {selectedFile.name}</p>
                        <p><strong>Размер:</strong> {(selectedFile.size / 1024).toFixed(2)} КБ</p>
                    </div>
                )}

                {error && <p className="error">{error}</p>}
            </div>

            <div className="actions">
                <button onClick={onBack} className="btn btn-secondary">
                    Назад
                </button>
                <button
                    onClick={handleUpload}
                    disabled={!selectedFile}
                    className="btn btn-primary"
                >
                    Продолжить
                </button>
            </div>
        </div>
    );
}

