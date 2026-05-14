export type SupportTicketPriority = 'Low' | 'Medium' | 'High' | 'Urgent';

export type SupportTicketStatus =
  | 'Open'
  | 'In Progress'
  | 'Pending Customer'
  | 'Resolved'
  | 'Closed';

export interface SupportTicketListItem {
  id: string;
  subject: string;
  submeta: string;
  customer: string;
  priority: SupportTicketPriority;
  status: SupportTicketStatus;
  agent: string;
  updated: string;
  requester: string;
  team: string;
  source: string;
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
  authorId: string | null;
  authorName: string | null;
  visibility: 'public' | 'internal';
  isInternal: boolean;
  relatedEntityId: string | null;
  parentActivityId: string | null;
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
  attachmentIds?: string[];
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
