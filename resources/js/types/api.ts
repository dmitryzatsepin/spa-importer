export interface SmartProcess {
    id: number;
    title: string;
    code: string | null;
}

export interface SmartProcessField {
    code: string;
    title: string;
    type: string;
    isRequired: boolean;
    isReadOnly: boolean;
}

export interface FieldMapping {
    source: string;
    target: string;
}

export interface ImportSettings {
    duplicate_handling?: 'skip' | 'update' | 'create_new';
    duplicate_field?: string;
    batch_size?: number;
}

export interface ImportJobStatus {
    job_id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    original_filename: string;
    total_rows: number;
    processed_rows: number;
    progress_percentage: number;
    error_details: string | null;
    created_at: string;
    updated_at: string;
}

export interface AppConfig {
    member_id?: string;
    domain?: string;
    portal_id?: number;
}

