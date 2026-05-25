export type ApiResponse<T> = {
  data: T;
  message?: string;
  status?: number;
};

export type ApiError = {
  message: string;
  status?: number;
  errors?: Record<string, string[]>;
};
