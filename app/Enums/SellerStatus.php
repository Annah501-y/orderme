<?php

namespace App\Enums;

enum SellerStatus: string
{
    case PENDING='pending';
    case APPROVED='approved';
    case REJECTED='rejected';
    case SUSPENDED='suspended';
}
