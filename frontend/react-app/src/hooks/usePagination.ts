import { useState } from 'react';

export function usePagination(initialPage = 1, initialPerPage = 10) {
  const [page, setPage] = useState(initialPage);
  const [perPage, setPerPage] = useState(initialPerPage);

  return {
    page,
    perPage,
    setPage,
    setPerPage,
  };
}
