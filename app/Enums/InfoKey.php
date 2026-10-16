<?php

namespace App\Enums;

enum InfoKey: string
{
    case Facility = 'facility';
    case Location = 'location';
    case VisitDate = 'visit_date';
    case Doctor = 'doctor';
    case BookingMethod = 'booking_method';
    case Branch = 'branch';

    public function label(): string
    {
        return match ($this) {
            self::Facility => 'Facility',
            self::Location => 'Location',
            self::VisitDate => 'Visit date',
            self::Doctor => 'Doctor',
            self::BookingMethod => 'Booking method',
            self::Branch => 'Branch',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
