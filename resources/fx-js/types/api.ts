export interface ApiResponse<T = any> {
  data?: T;
  message?: string;
}

export interface ValidationErrors {
  message: string;
  errors: Record<string, string[]>;
}

export interface ApiError {
  response?: {
    status: number;
    data: ValidationErrors | any;
  };
  message?: string;
}

export type ApiErrors = Record<string, string>;

export interface UseServerApiCallOptions {
  onSuccess?: (data: any) => void;
  onError?: (errors: ApiErrors) => void;
}

export interface UseServerApiCallReturn<TData = any, TVariables = any> {
  mutate: (data: TVariables) => Promise<void>;
  isSubmitting: boolean;
  isError: boolean;
  errors: ApiErrors;
  data: TData | null;
  reset: () => void;
}

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface ApiCallConfig {
  method?: HttpMethod;
  headers?: Record<string, string>;
}
