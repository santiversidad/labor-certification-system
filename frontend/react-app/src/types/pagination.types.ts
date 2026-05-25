export type PaginationMeta = {
  currentPage: number;
  perPage: number;
  total: number;
  lastPage: number;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta: PaginationMeta;
};
