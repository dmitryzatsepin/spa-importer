import axios from 'axios';
import type { SmartProcess, SmartProcessField, FieldMapping, ImportSettings, ImportJobStatus } from '../types/api';

const API_BASE = '/api/v1';

export const api = {
    async getSmartProcesses(portalId: number): Promise<SmartProcess[]> {
        const response = await axios.get(`${API_BASE}/smart-processes`, {
            params: { portal_id: portalId }
        });
        return response.data.data;
    },

    async getSmartProcessFields(entityTypeId: number, portalId: number): Promise<SmartProcessField[]> {
        const response = await axios.get(`${API_BASE}/smart-processes/${entityTypeId}/fields`, {
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

        const response = await axios.post(`${API_BASE}/import`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        return response.data.data;
    },

    async getImportStatus(jobId: number): Promise<ImportJobStatus> {
        const response = await axios.get(`${API_BASE}/import/${jobId}/status`);
        return response.data.data;
    }
};

