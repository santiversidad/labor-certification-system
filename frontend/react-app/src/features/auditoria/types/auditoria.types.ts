import type { AuditAction } from '../../../types/common.types';

export type AuditLog = {
  id: string;
  userId: string;
  userName: string;
  action: AuditAction;
  module: string;
  entity?: string;
  ip?: string;
  description: string;
  createdAt: string;
};
