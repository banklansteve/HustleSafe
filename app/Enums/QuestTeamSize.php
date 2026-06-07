<?php

namespace App\Enums;

enum QuestTeamSize: string
{
    case Solo = 'solo';
    case SmallTeam = 'small_team';
    case FlexibleCrew = 'flexible_crew';
    case ClientAssists = 'client_assists';
    /** @deprecated Removed from create UI — kept for legacy quests */
    case FreelancerArranges = 'freelancer_arranges';
}
