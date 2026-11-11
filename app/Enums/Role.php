<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case QualityAssurance = 'emotion_qa';
    case HospitalAdmin = 'hospital_admin';
    case HospitalViewer = 'hospital_viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'eMotion admin',
            self::QualityAssurance => 'eMotion QA',
            self::HospitalAdmin => 'Hospital admin',
            self::HospitalViewer => 'Viewer',
        };
    }

    /** eMotion staff roles do not belong to a hospital. */
    public function isHospitalRole(): bool
    {
        return ! in_array($this, self::staffRoles(), true);
    }

    /** @return list<self> */
    public static function staffRoles(): array
    {
        return [self::SuperAdmin, self::QualityAssurance];
    }

    /** @return list<self> */
    public static function hospitalRoles(): array
    {
        return [self::HospitalAdmin, self::HospitalViewer];
    }
}
