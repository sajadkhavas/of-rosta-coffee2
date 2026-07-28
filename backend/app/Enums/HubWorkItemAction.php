<?php

namespace App\Enums;

enum HubWorkItemAction: string
{
    case Created = 'created';
    case Received = 'receive';
    case Assigned = 'assign';
    case GrindingStarted = 'start_grinding';
    case QualitySubmitted = 'submit_quality_check';
    case QualityPassed = 'quality_pass';
    case QualityFailed = 'quality_fail';
    case ReworkStarted = 'restart_grinding';
    case ReadyForOutbound = 'mark_ready';
    case HandedOff = 'handoff';
    case Cancelled = 'cancel';
}
