<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';
    case FINISHED = 'finished';
}