<?php

namespace App\Support\Enums;

enum TicketStatus: string
{
    case Open = 'Open';
    case InProgress = 'In Progress';
    case PendingCustomer = 'Pending Customer';
    case Resolved = 'Resolved';
    case Closed = 'Closed';
}
