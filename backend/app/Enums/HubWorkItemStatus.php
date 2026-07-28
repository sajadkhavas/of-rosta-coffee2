<?php

namespace App\Enums;

enum HubWorkItemStatus: string
{
    case AwaitingInbound = 'awaiting_inbound';
    case Received = 'received';
    case Assigned = 'assigned';
    case Grinding = 'grinding';
    case QualityCheck = 'quality_check';
    case ReworkRequired = 'rework_required';
    case Packaging = 'packaging';
    case ReadyForOutbound = 'ready_for_outbound';
    case HandedOff = 'handed_off';
    case Cancelled = 'cancelled';

    public function publicLabel(): string
    {
        return match ($this) {
            self::AwaitingInbound => 'در انتظار رسیدن به هاب رستا',
            self::Received => 'در هاب رستا دریافت شد',
            self::Assigned => 'در صف آماده‌سازی هاب رستا',
            self::Grinding => 'آسیاب در هاب رستا در حال انجام است',
            self::QualityCheck => 'کنترل کیفیت آسیاب در حال انجام است',
            self::ReworkRequired => 'در حال اصلاح کنترل‌شده در هاب رستا',
            self::Packaging => 'بسته‌بندی رایگان هاب رستا در حال انجام است',
            self::ReadyForOutbound => 'آماده تحویل به حمل از هاب رستا',
            self::HandedOff => 'از هاب رستا به حمل تحویل شد',
            self::Cancelled => 'عملیات هاب متوقف شد',
        };
    }

    public function terminal(): bool
    {
        return in_array($this, [self::HandedOff, self::Cancelled], true);
    }
}
