import axios, { AxiosInstance, AxiosError, AxiosResponse } from 'axios';
import type { SmartProcess, SmartProcessField, FieldMapping, ImportSettings, ImportJobStatus, HistoryResponse, AppConfig } from '../types/api';

// Глобальная переменная для хранения конфигурации
let appConfig: AppConfig = {};

// Проверка окружения для логирования
const isProd = process.env.NODE_ENV === 'production';

// Функция для инициализации конфигурации
export const initApiConfig = (config: AppConfig): void => {
    appConfig = config;
};

// Создание axios instance с базовой конфигурацией
const createApiInstance = (): AxiosInstance => {
    const instance = axios.create({
        baseURL: appConfig.api_base_url || '/api/v1',
        timeout: 30000, // 30 секунд
        headers: {
            'Content-Type': 'application/json',
        },
    });

    // Request interceptor для логирования запросов
    instance.interceptors.request.use(
        (config) => {
            if (!isProd) console.log(`[API Request] ${config.method?.toUpperCase()} ${config.url}`, {
                params: config.params,
                data: config.data,
            });
            return config;
        },
        (error) => {
            if (!isProd) console.error('[API Request Error]', error);
            return Promise.reject(error);
        }
    );

    // Response interceptor для обработки ответов и ошибок
    instance.interceptors.response.use(
        (response: AxiosResponse) => {
            if (!isProd) console.log(`[API Response] ${response.config.method?.toUpperCase()} ${response.config.url}`, {
                status: response.status,
                data: response.data,
            });
            return response;
        },
        (error: AxiosError) => {
            const errorInfo = {
                message: error.message,
                status: error.response?.status,
                statusText: error.response?.statusText,
                url: error.config?.url,
                method: error.config?.method,
                data: error.response?.data,
            };

            if (!isProd) console.error('[API Error]', errorInfo);

            // Централизованная обработка различных типов ошибок
            if (error.response) {
                // Сервер ответил с кодом ошибки
                switch (error.response.status) {
                    case 401:
                        if (!isProd) console.error('[API] Неавторизованный доступ. Требуется повторная авторизация.');
                        // Здесь можно добавить логику перенаправления на страницу входа
                        break;
                    case 403:
                        if (!isProd) console.error('[API] Доступ запрещен. Недостаточно прав.');
                        break;
                    case 404:
                        if (!isProd) console.error('[API] Ресурс не найден.');
                        break;
                    case 422:
                        if (!isProd) console.error('[API] Ошибка валидации данных:', error.response.data);
                        break;
                    case 500:
                        if (!isProd) console.error('[API] Внутренняя ошибка сервера.');
                        break;
                    case 502:
                    case 503:
                    case 504:
                        if (!isProd) console.error('[API] Сервер временно недоступен.');
                        break;
                    default:
                        if (!isProd) console.error(`[API] Неизвестная ошибка сервера: ${error.response.status}`);
                }
            } else if (error.request) {
                // Запрос был отправлен, но ответ не получен
                if (!isProd) console.error('[API] Сетевая ошибка. Сервер не отвечает.');
            } else {
                // Ошибка при настройке запроса
                if (!isProd) console.error('[API] Ошибка конфигурации запроса:', error.message);
            }

            return Promise.reject(error);
        }
    );

    return instance;
};

// Получение экземпляра API
let apiInstance: AxiosInstance | null = null;

const getApiInstance = (): AxiosInstance => {
    if (!apiInstance) {
        apiInstance = createApiInstance();
    }
    return apiInstance;
};

// Обновление baseURL при изменении конфигурации
export const updateApiBaseUrl = (baseUrl: string): void => {
    appConfig.api_base_url = baseUrl;
    apiInstance = createApiInstance(); // Пересоздаем instance с новым baseURL
};

export const api = {
    async getSmartProcesses(portalId: number): Promise<SmartProcess[]> {
        const response = await getApiInstance().get('/smart-processes', {
            params: { portal_id: portalId }
        });
        return response.data.data;
    },

    async getSmartProcessFields(entityTypeId: number, portalId: number): Promise<SmartProcessField[]> {
        const response = await getApiInstance().get(`/smart-processes/${entityTypeId}/fields`, {
            params: { portal_id: portalId }
        });
        return response.data.data;
    },

    async startImport(
        file: File,
        portalId: number,
        entityTypeId: number,
        fieldMappings: FieldMapping[],
        settings?: ImportSettings
    ): Promise<{ job_id: number }> {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('portal_id', portalId.toString());
        formData.append('entity_type_id', entityTypeId.toString());

        fieldMappings.forEach((mapping, index) => {
            formData.append(`field_mappings[${index}][source]`, mapping.source);
            formData.append(`field_mappings[${index}][target]`, mapping.target);
        });

        if (settings) {
            Object.entries(settings).forEach(([key, value]) => {
                formData.append(`settings[${key}]`, value.toString());
            });
        }

        const response = await getApiInstance().post('/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        return response.data.data;
    },

    async getImportStatus(jobId: number): Promise<ImportJobStatus> {
        const response = await getApiInstance().get(`/import/${jobId}/status`);
        return response.data.data;
    },

    async getImportHistory(portalId: number): Promise<HistoryResponse> {
        const response = await getApiInstance().get('/import/history', {
            params: { portal_id: portalId }
        });
        return {
            data: response.data.data,
            pagination: response.data.pagination
        };
    },

    downloadErrorLog(jobId: number): void {
        const baseUrl = appConfig.api_base_url || '/api/v1';
        window.location.href = `${baseUrl}/import/${jobId}/error-log`;
    }
};