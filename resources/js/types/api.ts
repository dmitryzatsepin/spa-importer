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

export interface ImportJobHistoryItem {
    job_id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    original_filename: string;
    total_rows: number;
    processed_rows: number;
    progress_percentage: number;
    has_errors: boolean;
    error_count: number;
    created_at: string;
    updated_at: string;
}

export interface PaginationInfo {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface HistoryResponse {
    data: ImportJobHistoryItem[];
    pagination: PaginationInfo;
}

export interface AppConfig {
    member_id?: string;
    domain?: string;
    portal_id?: number;
    api_base_url?: string;
}

