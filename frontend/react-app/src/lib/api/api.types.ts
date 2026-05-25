export type ApiResponse<T> = {
  success: boolean;
  data: T;
  message?: string;
};

export type ApiError = {
  message: string;
  status?: number;
  errors?: Record<string, string[]>;
};
