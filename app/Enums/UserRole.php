<?php

namespace App\Enums;

enum UserRole:string
{
    case BUYER='buyer';
    case SELLER= 'seller';
    case ADMIN= 'admin';
}
