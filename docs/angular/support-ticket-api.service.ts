import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

export type TicketPriority = 'Low' | 'Medium' | 'High' | 'Urgent';

export type TicketStatus =
  | 'Open'
  | 'In Progress'
  | 'Pending Customer'
  | 'Resolved'
  | 'Closed';

export type TicketSort =
  | 'updated_desc'
  | 'updated_asc'
  | 'priority_desc'
  | 'priority_asc'
  | 'status_asc'
  | 'customer_asc'
  | 'subject_asc';

export interface TicketListParams {
  search?: string;
  queue?: string;
  priority?: TicketPriority;
  status?: TicketStatus;
  agent?: string;
  sort?: TicketSort;
  page?: number;
  pageSize?: number;
}

export interface AttachmentListParams {
  search?: string;
  visibility?: 'public' | 'internal';
  ticketId?: string;
  page?: number;
  pageSize?: number;
}

export interface TicketListItem {
  id: string;
  subject: string;
  submeta: string;
  customer: string;
  priority: TicketPriority;
  status: TicketStatus;
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

export interface TicketActivity {
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
  mentions: TicketActivityMention[];
}

export interface TicketActivityMention {
  id: string;
  display: string;
  kind: 'user' | 'team';
}

export interface TicketRelatedItem {
  id: string;
  title: string;
  meta: string;
}

export interface TicketDetail extends TicketListItem {
  slaFirstResponse: string;
  slaResolution: string;
  activities: TicketActivity[];
  relatedItems: TicketRelatedItem[];
  forwardState: {
    lastMode: 'team' | 'external' | 'task' | null;
    lastForwardedTo: string | null;
    lastForwardedAt: string | null;
    lastForwardedBy: string | null;
  };
  linkedTaskSummary: {
    count: number;
    openCount: number;
  };
  attachmentSummary: {
    count: number;
  };
  linkedTasks: TicketLinkedTask[];
  attachments: TicketAttachment[];
}

export interface TicketLinkedTask {
  id: string;
  title: string;
  assignee: string | null;
  status: string;
  createdAt: string;
  updatedAt: string;
}

export interface TicketAttachment {
  id: string;
  fileName: string;
  size: number;
  uploadedBy: string;
  uploadedAt: string;
  visibility: 'public' | 'internal';
  ticketId: string | null;
  ticketSubject: string | null;
}

export interface UploadAttachmentPayload {
  file: File | Blob;
  visibility: 'public' | 'internal';
  ticketId?: string | null;
  customer?: string | null;
  requester?: string | null;
}

export interface PaginatedAttachmentsResponse {
  items: TicketAttachment[];
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
  priorities: TicketPriority[];
  statuses: TicketStatus[];
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

export interface LoginPayload {
  email: string;
  password: string;
  deviceName?: string;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  deviceName?: string;
}

export interface PaginatedTicketsResponse {
  items: TicketListItem[];
  total: number;
  page: number;
  pageSize: number;
}

export interface BulkAssignPayload {
  ticketIds: string[];
  agent: string;
}

export interface BulkUpdateStatusPayload {
  ticketIds: string[];
  status: TicketStatus;
}

export interface BulkUpdatePriorityPayload {
  ticketIds: string[];
  priority: TicketPriority;
}

export interface BulkActionResponse {
  success: boolean;
  updatedCount: number;
}

export interface ReplyToTicketPayload {
  message: string;
  isInternalNote: boolean;
  attachmentIds?: string[];
  parentActivityId?: string | null;
  mentions?: TicketActivityMention[];
}

export interface CreateTicketPayload {
  subject: string;
  customer: string;
  requester: string;
  team: string;
  category: string;
  priority: TicketPriority;
  message?: string | null;
  body?: string | null;
  attachmentIds?: string[];
}

export interface CreateTicketResponse {
  success: boolean;
  ticketId: string;
}

export interface ReplyToTicketResponse {
  success: boolean;
  activityId: string;
}

export interface ForwardTicketPayload {
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

export interface ForwardTicketResponse {
  success: boolean;
  forwardId: string;
  linkedTaskId: string | null;
  message: string;
}

@Injectable({
  providedIn: 'root',
})
export class SupportTicketApiService {
  private readonly baseUrl = '/api/support/tickets';
  private readonly attachmentBaseUrl = '/api/support/attachments';
  private readonly supportBaseUrl = '/api/support';
  private readonly authBaseUrl = '/api/auth';

  constructor(private readonly http: HttpClient) {}

  getTickets(params: TicketListParams = {}): Observable<PaginatedTicketsResponse> {
    return this.http.get<PaginatedTicketsResponse>(this.baseUrl, {
      params: this.buildQueryParams(params),
    });
  }

  login(payload: LoginPayload): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.authBaseUrl}/login`, payload);
  }

  register(payload: RegisterPayload): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.authBaseUrl}/register`, payload);
  }

  getMe(): Observable<{ user: AuthUser }> {
    return this.http.get<{ user: AuthUser }>(`${this.authBaseUrl}/me`);
  }

  logout(): Observable<{ success: boolean }> {
    return this.http.post<{ success: boolean }>(`${this.authBaseUrl}/logout`, {});
  }

  getAttachments(params: AttachmentListParams = {}): Observable<PaginatedAttachmentsResponse> {
    return this.http.get<PaginatedAttachmentsResponse>(this.attachmentBaseUrl, {
      params: this.buildQueryParams(params),
    });
  }

  uploadAttachment(payload: UploadAttachmentPayload): Observable<TicketAttachment> {
    const formData = new FormData();
    formData.append('file', payload.file);
    formData.append('visibility', payload.visibility);

    if (payload.ticketId) {
      formData.append('ticketId', payload.ticketId);
    }

    if (payload.customer) {
      formData.append('customer', payload.customer);
    }

    if (payload.requester) {
      formData.append('requester', payload.requester);
    }

    return this.http.post<TicketAttachment>(`${this.attachmentBaseUrl}/upload`, formData);
  }

  getReferenceData(): Observable<SupportReferenceData> {
    return this.http.get<SupportReferenceData>(`${this.supportBaseUrl}/reference-data`);
  }

  getTeams(): Observable<SupportReferenceItem[]> {
    return this.http.get<SupportReferenceItem[]>(`${this.supportBaseUrl}/teams`);
  }

  getCategories(): Observable<SupportReferenceItem[]> {
    return this.http.get<SupportReferenceItem[]>(`${this.supportBaseUrl}/categories`);
  }

  getAgents(): Observable<SupportAgentReferenceItem[]> {
    return this.http.get<SupportAgentReferenceItem[]>(`${this.supportBaseUrl}/agents`);
  }

  createTicket(payload: CreateTicketPayload): Observable<CreateTicketResponse> {
    return this.http.post<CreateTicketResponse>(this.baseUrl, payload);
  }

  getTicketById(id: string): Observable<TicketDetail> {
    return this.http.get<TicketDetail>(`${this.baseUrl}/${encodeURIComponent(id)}`);
  }

  getLinkedTasks(id: string): Observable<TicketLinkedTask[]> {
    return this.http.get<TicketLinkedTask[]>(`${this.baseUrl}/${encodeURIComponent(id)}/linked-tasks`);
  }

  getTicketAttachments(id: string, params: Omit<AttachmentListParams, 'ticketId'> = {}): Observable<PaginatedAttachmentsResponse> {
    return this.http.get<PaginatedAttachmentsResponse>(`${this.baseUrl}/${encodeURIComponent(id)}/attachments`, {
      params: this.buildQueryParams(params),
    });
  }

  bulkAssign(payload: BulkAssignPayload): Observable<BulkActionResponse> {
    return this.http.post<BulkActionResponse>(`${this.baseUrl}/bulk/assign`, payload);
  }

  bulkUpdateStatus(payload: BulkUpdateStatusPayload): Observable<BulkActionResponse> {
    return this.http.post<BulkActionResponse>(`${this.baseUrl}/bulk/status`, payload);
  }

  bulkUpdatePriority(payload: BulkUpdatePriorityPayload): Observable<BulkActionResponse> {
    return this.http.post<BulkActionResponse>(`${this.baseUrl}/bulk/priority`, payload);
  }

  replyToTicket(id: string, payload: ReplyToTicketPayload): Observable<ReplyToTicketResponse> {
    return this.http.post<ReplyToTicketResponse>(
      `${this.baseUrl}/${encodeURIComponent(id)}/reply`,
      payload,
    );
  }

  forwardTicket(id: string, payload: ForwardTicketPayload): Observable<ForwardTicketResponse> {
    return this.http.post<ForwardTicketResponse>(
      `${this.baseUrl}/${encodeURIComponent(id)}/forward`,
      payload,
    );
  }

  private buildQueryParams(params: Record<string, string | number | boolean | null | undefined>): HttpParams {
    return Object.entries(params).reduce((httpParams, [key, value]) => {
      if (value === undefined || value === null || value === '') {
        return httpParams;
      }

      return httpParams.set(key, String(value));
    }, new HttpParams());
  }
}
