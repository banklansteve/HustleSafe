<?php

namespace App\Enums;

enum QuestFreelancerLocationPref: string
{
    case RemoteFriendly = 'remote_friendly';
    case LocalOnly = 'local_only';
}
