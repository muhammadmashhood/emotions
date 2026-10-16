<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case HospitalAdmin = 'hospital_admin';
    case HospitalViewer = 'hospital_viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::HospitalAdmin => 'Hospital admin',
            self::HospitalViewer => 'Viewer',
        };
    }

    public function isHospitalRole(): bool
    {
        return $this !== self::SuperAdmin;
    }

    /** @return list<self> */
    public static function hospitalRoles(): array
    {
        return [self::HospitalAdmin, self::HospitalViewer];
    }
}
