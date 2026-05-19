export type SupportTicketPriority = 'Low' | 'Medium' | 'High' | 'Urgent';

export type SupportTicketStatus =
  | 'Open'
  | 'In Progress'
  | 'Pending Customer'
  | 'Resolved'
  | 'Closed';

export type SupportTicketCreatedByType = 'Customer' | 'Agent' | 'Admin' | 'System';

export type SupportTicketSlaState = 'on_track' | 'at_risk' | 'breached' | 'met' | 'unknown';

export interface SupportTicketListItem {
  id: string;
  subject: string;
  submeta: string;
  customer: string;
  priority: SupportTicketPriority;
  status: SupportTicketStatus;
  agent: string;
  updated: string;
  updatedAt: string;
  requester: string;
  team: string;
  source: string;
  createdByType: SupportTicketCreatedByType;
  category: string;
  isAssignedToMe: boolean;
  isWaitingOnCustomer: boolean;
  isForwarded: boolean;
  forwardMode: 'team' | 'external' | 'task' | null;
  forwardTarget: string | null;
  hasLinkedTask: boolean;
  linkedTaskCount: number;
  hasAttachments: boolean;
  attachmentCount: number;
  waitingOn: 'team' | 'customer' | 'external' | null;
  slaState: SupportTicketSlaState;
}

export interface SupportTicketListResponse {
  items: SupportTicketListItem[];
  total: number;
  page: number;
  pageSize: number;
}

export interface SupportTicketActivity {
  id: string;
  title: string;
  time: string;
  type: string;
  body: string | null;
  htmlBody?: string | null;
  authorId: string | null;
  authorName: string | null;
  authorEmail?: string | null;
  senderType?: 'Requester' | 'Agent' | 'Admin' | 'System' | null;
  visibility: 'public' | 'internal';
  isInternal: boolean;
  relatedEntityId: string | null;
  parentActivityId: string | null;
  createdAt?: string | null;
  recipients?: {
    to: string[];
    cc: string[];
    bcc: string[];
  };
  deliveryStatus?: string | null;
  deliveredAt?: string | null;
  failedReason?: string | null;
  mentions: SupportTicketActivityMention[];
}

export interface SupportTicketActivityMention {
  id: string;
  display: string;
  kind: 'user' | 'team';
}

export interface SupportTicketForwardState {
  lastMode: 'team' | 'external' | 'task' | null;
  lastForwardedTo: string | null;
  lastForwardedAt: string | null;
  lastForwardedBy: string | null;
}

export interface SupportTicketLinkedTaskSummary {
  count: number;
  openCount: number;
}

export interface SupportTicketAttachmentSummary {
  count: number;
}

export interface SupportTicketLinkedTask {
  id: string;
  title: string;
  assignee: string | null;
  status: 'Open' | 'In Progress' | 'Closed' | string;
  createdAt: string;
  updatedAt: string;
}

export interface SupportTicketAttachment {
  id: string;
  fileName: string;
  size: number;
  uploadedBy: string;
  uploadedAt: string;
  visibility: 'public' | 'internal';
  ticketId: string | null;
  ticketSubject: string | null;
}

export interface UploadSupportAttachmentRequest {
  file: File | Blob;
  visibility: 'public' | 'internal';
  ticketId?: string | null;
  customer?: string | null;
  requester?: string | null;
}

export interface SupportAttachmentListParams {
  search?: string;
  visibility?: 'public' | 'internal';
  ticketId?: string;
  page?: number;
  pageSize?: number;
}

export interface SupportAttachmentListResponse {
  items: SupportTicketAttachment[];
  total: number;
  page: number;
  pageSize: number;
}

export interface SupportReferenceItem {
  id: string;
  name: string;
  isActive: boolean;
}

export interface SupportAgentReferenceItem extends SupportReferenceItem {
  email: string | null;
}

export interface SupportReferenceData {
  teams: SupportReferenceItem[];
  categories: SupportReferenceItem[];
  agents: SupportAgentReferenceItem[];
  queues: SupportReferenceItem[];
  priorities: SupportTicketPriority[];
  statuses: SupportTicketStatus[];
  sources: string[];
}

export interface AuthUser {
  id: string;
  name: string;
  email: string | null;
}

export interface AuthResponse {
  token: string;
  tokenType: 'Bearer';
  expiresAt: string;
  user: AuthUser;
}

export interface LoginRequest {
  email: string;
  password: string;
  deviceName?: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  deviceName?: string;
}

export interface SupportTicketRelatedItem {
  id: string;
  title: string;
  meta: string;
}

export interface SupportTicketDetail extends SupportTicketListItem {
  slaFirstResponse: string;
  slaResolution: string;
  activities: SupportTicketActivity[];
  relatedItems: SupportTicketRelatedItem[];
  forwardState: SupportTicketForwardState;
  linkedTaskSummary: SupportTicketLinkedTaskSummary;
  attachmentSummary: SupportTicketAttachmentSummary;
  linkedTasks: SupportTicketLinkedTask[];
  attachments: SupportTicketAttachment[];
}

export interface CreateSupportTicketRequest {
  subject: string;
  customer: string;
  requester: string;
  team: string;
  category: string;
  priority: SupportTicketPriority;
  message?: string | null;
  body?: string | null;
  createdByType?: SupportTicketCreatedByType;
  attachmentIds?: string[];
}

export interface SupportTicketSyncSnapshot {
  id: string;
  ticketId: string;
  status: SupportTicketStatus;
  priority: SupportTicketPriority;
  createdByType: SupportTicketCreatedByType;
  updatedAt: string;
  waitingOn: 'team' | 'customer' | 'external' | null;
  slaState: SupportTicketSlaState;
}

export interface CreateSupportTicketResponse {
  success: true;
  id: string;
  ticketId: string;
  ticket: SupportTicketListItem;
}

export interface BulkAssignRequest {
  ticketIds: string[];
  agent: string;
}

export interface BulkStatusRequest {
  ticketIds: string[];
  status: SupportTicketStatus;
}

export interface BulkPriorityRequest {
  ticketIds: string[];
  priority: SupportTicketPriority;
}

export interface TicketReplyRequest {
  message: string;
  isInternalNote: boolean;
  attachmentIds?: string[];
  parentActivityId?: string | null;
  mentions?: SupportTicketActivityMention[];
}

export interface TicketForwardRequest {
  mode: 'team' | 'external' | 'task';
  to?: string | null;
  teamId?: string | null;
  comment?: string | null;
  includeAttachments?: boolean;
  attachmentIds?: string[];
  createLinkedTask?: boolean;
  taskTitle?: string | null;
  taskAssignee?: string | null;
}

export type NotificationDispatchEvent = 'reply' | 'email' | 'forward' | 'internal_mention';

export interface DispatchTicketNotificationsRequest {
  event: NotificationDispatchEvent;
  activityId: string;
  channels?: Array<'email' | 'in_app' | 'push' | 'webhook'>;
}

export interface DispatchTicketNotificationsResponse {
  queuedJobIds: string[];
  activityId: string;
  recipients: Partial<Record<NotificationDispatchEvent, string[]>>;
}

export interface SupportTicketMutationResponse {
  success: true;
  updatedCount?: number;
  activityId?: string;
  createdAt?: string | null;
  forwardId?: string;
  linkedTaskId?: string | null;
  message?: string;
  ticket?: SupportTicketSyncSnapshot | null;
  tickets?: SupportTicketSyncSnapshot[];
}

export type ApiErrorCode =
  | 'VALIDATION_ERROR'
  | 'AUTH_UNAUTHENTICATED'
  | 'AUTH_FORBIDDEN'
  | 'ACCOUNT_INACTIVE'
  | 'ACCOUNT_LOCKED'
  | 'RESOURCE_NOT_FOUND'
  | 'SECURITY_VERSION_CONFLICT'
  | 'METHOD_NOT_ALLOWED'
  | 'TOO_MANY_REQUESTS'
  | 'DATABASE_ERROR'
  | 'SERVER_ERROR'
  | string;

export interface ApiErrorMeta {
  requestId: string;
  timestamp: string;
  path: string;
  method: string;
}

export interface ApiErrorBody {
  id: string;
  status: number;
  code: ApiErrorCode;
  message: string;
  details?: Record<string, unknown>;
  errors?: Record<string, string[]>;
}

export interface ApiErrorResponse {
  success: false;
  code: ApiErrorCode;
  message: string;
  details?: Record<string, unknown>;
  errors?: Record<string, string[]>;
  error: ApiErrorBody;
  meta: ApiErrorMeta;
}

export interface UiErrorDialogConfig {
  title: string;
  userMessage: string;
  severity: 'error' | 'warning' | 'info';
  recommendedAction?: 'retry' | 'refresh' | 'contact_admin' | 'relogin' | 'none';
}

export const API_ERROR_DIALOG_MAP: Partial<Record<ApiErrorCode, UiErrorDialogConfig>> = {
  VALIDATION_ERROR: {
    title: 'Validation Failed',
    userMessage: 'Please correct the highlighted fields and try again.',
    severity: 'warning',
    recommendedAction: 'none',
  },
  AUTH_UNAUTHENTICATED: {
    title: 'Session Expired',
    userMessage: 'Please sign in again to continue.',
    severity: 'warning',
    recommendedAction: 'relogin',
  },
  AUTH_FORBIDDEN: {
    title: 'Access Denied',
    userMessage: 'You do not have permission to perform this action.',
    severity: 'error',
    recommendedAction: 'contact_admin',
  },
  ACCOUNT_INACTIVE: {
    title: 'Account Inactive',
    userMessage: 'Your account is inactive. Contact admin.',
    severity: 'error',
    recommendedAction: 'contact_admin',
  },
  ACCOUNT_LOCKED: {
    title: 'Account Locked',
    userMessage: 'Your account is temporarily locked. Contact admin if needed.',
    severity: 'error',
    recommendedAction: 'contact_admin',
  },
  RESOURCE_NOT_FOUND: {
    title: 'Not Found',
    userMessage: 'The requested record was not found or no longer exists.',
    severity: 'warning',
    recommendedAction: 'refresh',
  },
  SECURITY_VERSION_CONFLICT: {
    title: 'Outdated Data',
    userMessage: 'This record changed in another session. Reload and apply changes again.',
    severity: 'warning',
    recommendedAction: 'refresh',
  },
  TOO_MANY_REQUESTS: {
    title: 'Too Many Requests',
    userMessage: 'Please wait a moment and retry.',
    severity: 'warning',
    recommendedAction: 'retry',
  },
  DATABASE_ERROR: {
    title: 'System Error',
    userMessage: 'A data processing error occurred. Please retry in a moment.',
    severity: 'error',
    recommendedAction: 'retry',
  },
  SERVER_ERROR: {
    title: 'Unexpected Error',
    userMessage: 'Something went wrong on the server. Please retry.',
    severity: 'error',
    recommendedAction: 'retry',
  },
};
