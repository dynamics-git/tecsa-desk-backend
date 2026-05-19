<?php

namespace App\Support\Enums;

enum TicketCreatedByType: string
{
    case Customer = 'Customer';
    case Agent = 'Agent';
    case Admin = 'Admin';
    case System = 'System';
}
