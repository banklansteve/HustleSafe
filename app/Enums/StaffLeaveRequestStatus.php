<?php

namespace App\Enums;

enum StaffLeaveRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
