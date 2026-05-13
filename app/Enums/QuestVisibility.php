<?php

namespace App\Enums;

enum QuestVisibility: string
{
    case Public = 'public';
    case InviteOnly = 'invite_only';
    case Private = 'private';
}
