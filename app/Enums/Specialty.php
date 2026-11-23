<?php

namespace App\Enums;

enum Specialty: string
{
    case CARDIOLOGY = 'cardiology';
    case DERMATOLOGY = 'dermatology';
    case PEDIATRICS = 'pediatrics';
    case NEUROLOGY = 'neurology';
    case ORTHOPEDICS = 'orthopedics';
    case GENERAL = 'general';
}
