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
    case Recommend = 'recommend';

    public function label(): string
    {
        return match ($this) {
            self::Facility => 'Facility',
            self::Location => 'Location',
            self::VisitDate => 'Visit date',
            self::Doctor => 'Doctor',
            self::BookingMethod => 'Booking method',
            self::Branch => 'Branch',
            self::Recommend => 'Likelihood to recommend',
        };
    }

    /** Numeric KPI roles may be carried by several questions; their values are averaged. */
    public function allowsMultiple(): bool
    {
        return $this === self::Recommend;
    }

    /** Keys shown as header information on an evaluation (the KPI role is not display info). */
    public function isDisplayInfo(): bool
    {
        return $this !== self::Recommend;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
