import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './AppComponent';
import type { AppConfig } from './types/api';
import { initApiConfig } from './services/api';

const rootElement = document.getElementById('root');

if (rootElement) {
    const configElement = document.getElementById('app-config');
    let config: AppConfig = {};

    if (configElement) {
        try {
            config = JSON.parse(configElement.textContent || '{}');
            // Инициализируем API с конфигурацией
            initApiConfig(config);
        } catch (e) {
            console.error('Ошибка парсинга конфигурации:', e);
        }
    }

    const root = createRoot(rootElement);
    root.render(
        <React.StrictMode>
            <App config={config} />
        </React.StrictMode>
    );
}

